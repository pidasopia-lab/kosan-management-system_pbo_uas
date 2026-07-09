<?php
require_once 'config.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
         // ===== UPDATE FOTO KAMAR =====
case 'updateRoomImage':
    $roomNumber = (int)$_POST['room_number'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $targetDir = 'uploads/rooms/';
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = 'room_' . $roomNumber . '_' . time() . '.' . $ext;
        $targetFile = $targetDir . $filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
            // Hapus foto lama jika ada
            $stmt = $pdo->prepare("SELECT image_path FROM rooms WHERE room_number = ?");
            $stmt->execute([$roomNumber]);
            $old = $stmt->fetchColumn();
            if ($old && file_exists($old)) unlink($old);
            
            // Update database
            $pdo->prepare("UPDATE rooms SET image_path = ? WHERE room_number = ?")
                ->execute([$targetFile, $roomNumber]);
            echo json_encode(['success' => true, 'path' => $targetFile]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Gagal upload file']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Tidak ada file yang diupload']);
    }
    break;

    // ===== HAPUS FOTO KAMAR =====
case 'deleteRoomImage':
    $roomNumber = (int)$_GET['room_number'];
    $stmt = $pdo->prepare("SELECT image_path FROM rooms WHERE room_number = ?");
    $stmt->execute([$roomNumber]);
    $old = $stmt->fetchColumn();
    if ($old && file_exists($old)) unlink($old);
    $pdo->prepare("UPDATE rooms SET image_path = NULL WHERE room_number = ?")->execute([$roomNumber]);
    echo json_encode(['success' => true]);
    break;
    
        case 'getAllData':
            $basePrice = $pdo->query("SELECT base_price FROM settings LIMIT 1")->fetchColumn();
            
            $rooms = $pdo->query("SELECT room_number, is_occupied, image_path FROM rooms ORDER BY room_number")->fetchAll(PDO::FETCH_ASSOC);
$rooms = array_map(function($r) {
    return [
        'number' => (int)$r['room_number'],
        'occupied' => (bool)$r['is_occupied'],
        'image_path' => $r['image_path'] ?? null
    ];
}, $rooms);
            
            $facilities = $pdo->query("SELECT id, name, price, icon FROM facilities ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
            
            // Tenants
            $tenants = [];
            $stmt = $pdo->query("
                SELECT t.id, t.name, t.phone, r.room_number, t.start_month, t.paid_months
                FROM tenants t
                JOIN rooms r ON t.room_id = r.id
            ");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $facStmt = $pdo->prepare("SELECT f.name, f.price FROM tenant_facilities tf JOIN facilities f ON tf.facility_id = f.id WHERE tf.tenant_id = ?");
                $facStmt->execute([$row['id']]);
                $facilitiesTenant = $facStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $tenants[] = [
                    'id' => (int)$row['id'],
                    'name' => $row['name'],
                    'phone' => $row['phone'],
                    'room' => (int)$row['room_number'],
                    'startMonth' => (int)$row['start_month'],
                    'paidMonths' => json_decode($row['paid_months'] ?? '[]') ?: [],
                    'facilities' => $facilitiesTenant
                ];
            }
            
            
            // Payments
            $payments = [];
            $stmt = $pdo->query("
                SELECT p.payment_date, t.name as tenantName, r.room_number, p.months, p.amount, p.method
                FROM payments p
                JOIN tenants t ON p.tenant_id = t.id
                JOIN rooms r ON t.room_id = r.id
                ORDER BY p.payment_date DESC
            ");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $payments[] = [
                    'date' => $row['payment_date'],
                    'tenantName' => $row['tenantName'],
                    'room' => (int)$row['room_number'],
                    'months' => json_decode($row['months'] ?? '[]') ?: [],
                    'amount' => (int)$row['amount'],
                    'method' => $row['method']
                ];
            }
            
            // Registrations
            $registrations = [];
            $stmt = $pdo->query("SELECT * FROM registrations WHERE status = 'pending' ORDER BY created_at DESC");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $registrations[] = [
                    'id' => (int)$row['id'],
                    'date' => $row['created_at'],
                    'name' => $row['name'],
                    'phone' => $row['phone'],
                    'requestedRoom' => (int)$row['requested_room'],
                    'facilities' => json_decode($row['facilities'] ?? '[]') ?: [],
                    'totalPrice' => (int)$row['total_price']
                ];
            }
            
            echo json_encode([
                'basePrice' => (int)$basePrice,
                'rooms' => $rooms,
                'facilities' => $facilities,
                'tenants' => $tenants,
                'payments' => $payments,
                'registrations' => $registrations
            ]);
            break;
            
        case 'updateBasePrice':
            $data = json_decode(file_get_contents('php://input'), true);
            $price = (int)$data['price'];
            $pdo->prepare("UPDATE settings SET base_price = ?")->execute([$price]);
            echo json_encode(['success' => true]);
            break;
            
        case 'addFacility':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("INSERT INTO facilities (name, price, icon) VALUES (?, ?, ?)");
            $stmt->execute([$data['name'], $data['price'], $data['icon'] ?? 'plus-circle']);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;
            
        case 'deleteFacility':
            $id = (int)$_GET['id'];
            $pdo->prepare("DELETE FROM facilities WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
            break;
            
        case 'addTenant':
            $data = json_decode(file_get_contents('php://input'), true);
            $roomStmt = $pdo->prepare("SELECT id FROM rooms WHERE room_number = ?");
            $roomStmt->execute([$data['room']]);
            $room = $roomStmt->fetch(PDO::FETCH_ASSOC);
            if (!$room) throw new Exception('Kamar tidak ditemukan');
            
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO tenants (name, phone, room_id, start_month, paid_months) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$data['name'], $data['phone'], $room['id'], $data['startMonth'], json_encode($data['paidMonths'] ?? [])]);
            $tenantId = $pdo->lastInsertId();
            
            if (!empty($data['facilities'])) {
                $facStmt = $pdo->prepare("INSERT INTO tenant_facilities (tenant_id, facility_id) VALUES (?, ?)");
                foreach ($data['facilities'] as $fac) {
                    $fId = $pdo->prepare("SELECT id FROM facilities WHERE name = ?");
                    $fId->execute([$fac['name']]);
                    $fRow = $fId->fetch(PDO::FETCH_ASSOC);
                    if ($fRow) $facStmt->execute([$tenantId, $fRow['id']]);
                }
            }
            $pdo->prepare("UPDATE rooms SET is_occupied = 1 WHERE id = ?")->execute([$room['id']]);
            $pdo->commit();
            echo json_encode(['success' => true, 'tenantId' => $tenantId]);
            break;
            
        case 'deleteTenant':
            $id = (int)$_GET['id'];
            $pdo->beginTransaction();
            $roomStmt = $pdo->prepare("SELECT room_id FROM tenants WHERE id = ?");
            $roomStmt->execute([$id]);
            $room = $roomStmt->fetch(PDO::FETCH_ASSOC);
            if ($room) $pdo->prepare("UPDATE rooms SET is_occupied = 0 WHERE id = ?")->execute([$room['room_id']]);
            $pdo->prepare("DELETE FROM tenants WHERE id = ?")->execute([$id]);
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;
            
        case 'addPayment':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("SELECT t.id FROM tenants t JOIN rooms r ON t.room_id = r.id WHERE t.name = ? AND r.room_number = ?");
            $stmt->execute([$data['tenantName'], $data['room']]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$tenant) throw new Exception('Penyewa tidak ditemukan');
            
            $tenantId = $tenant['id'];
            $currentPaid = $pdo->prepare("SELECT paid_months FROM tenants WHERE id = ?");
            $currentPaid->execute([$tenantId]);
            $paidMonths = json_decode($currentPaid->fetchColumn() ?: '[]') ?: [];
            foreach ($data['months'] as $m) {
                if (!in_array($m, $paidMonths)) $paidMonths[] = $m;
            }
            sort($paidMonths);
            $pdo->prepare("UPDATE tenants SET paid_months = ? WHERE id = ?")->execute([json_encode($paidMonths), $tenantId]);
            
            $stmt = $pdo->prepare("INSERT INTO payments (tenant_id, amount, months, method) VALUES (?, ?, ?, ?)");
            $stmt->execute([$tenantId, $data['amount'], json_encode($data['months']), $data['method'] ?? 'cash']);
            echo json_encode(['success' => true]);
            break;
            
        case 'addRegistration':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("INSERT INTO registrations (name, phone, requested_room, facilities, total_price) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$data['name'], $data['phone'], $data['requestedRoom'], json_encode($data['facilities']), $data['totalPrice']]);
            echo json_encode(['success' => true]);
            break;
            
        case 'approveRegistration':
            $id = (int)$_GET['id'];
            $reg = $pdo->prepare("SELECT * FROM registrations WHERE id = ? AND status = 'pending'");
            $reg->execute([$id]);
            $regData = $reg->fetch(PDO::FETCH_ASSOC);
            if (!$regData) throw new Exception('Data tidak ditemukan atau sudah diproses');
            
            $roomStmt = $pdo->prepare("SELECT id FROM rooms WHERE room_number = ?");
            $roomStmt->execute([$regData['requested_room']]);
            $room = $roomStmt->fetch(PDO::FETCH_ASSOC);
            if (!$room) throw new Exception('Kamar tidak ditemukan');
            
            $check = $pdo->prepare("SELECT is_occupied FROM rooms WHERE id = ?");
            $check->execute([$room['id']]);
            if ($check->fetchColumn()) throw new Exception('Kamar sudah terisi');
            
            $pdo->beginTransaction();
            $facilities = json_decode($regData['facilities'] ?? '[]') ?: [];
            $stmt = $pdo->prepare("INSERT INTO tenants (name, phone, room_id, start_month, paid_months) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$regData['name'], $regData['phone'], $room['id'], date('n'), json_encode([])]);
            $tenantId = $pdo->lastInsertId();
            
            if (!empty($facilities)) {
                $facStmt = $pdo->prepare("INSERT INTO tenant_facilities (tenant_id, facility_id) VALUES (?, ?)");
                foreach ($facilities as $fac) {
                    $fId = $pdo->prepare("SELECT id FROM facilities WHERE name = ?");
                    $fId->execute([$fac['name']]);
                    $fRow = $fId->fetch(PDO::FETCH_ASSOC);
                    if ($fRow) $facStmt->execute([$tenantId, $fRow['id']]);
                }
            }
            $pdo->prepare("UPDATE rooms SET is_occupied = 1 WHERE id = ?")->execute([$room['id']]);
            $pdo->prepare("UPDATE registrations SET status = 'approved' WHERE id = ?")->execute([$id]);
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;
            
        case 'rejectRegistration':
            $id = (int)$_GET['id'];
            $pdo->prepare("UPDATE registrations SET status = 'rejected' WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['error' => 'Aksi tidak dikenal: ' . $action]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>