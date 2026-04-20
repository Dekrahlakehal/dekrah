<?php
require_once '../includes/auth.php';
require_login('etudiant');
$pdo = get_pdo();
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM planning WHERE id = ? AND etudiant_id = ?");
    $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
}
header('Location: ' . url('student/student.php'));
exit();
?>
