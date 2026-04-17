<?php
require_once 'includes/auth.php';
require_login('etudiant');
$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Add Assignment Logic
    if (isset($_POST['add_task'])) {
        $stmt = $pdo->prepare("INSERT INTO tasks (etudiant_id, titre, echeance, priorite) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'], 
            $_POST['titre'], 
            $_POST['echeance'], 
            $_POST['priorite']
        ]);
    } 
    // 2. Add Schedule Logic
    elseif (isset($_POST['add_schedule'])) {
        $stmt = $pdo->prepare("INSERT INTO planning (etudiant_id, jour, module_name, heure_debut, salle) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'], 
            $_POST['jour'], 
            $_POST['module_name'], 
            $_POST['heure_debut'], 
            $_POST['salle']
        ]);
    }
}

// Automatic return to your dashboard
header('Location: ' . url('student.php'));
exit;
