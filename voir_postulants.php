<?php
session_start();
require_once("config.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== "agriculteur") {
    header("Location: login.php");
    exit;
}

$id_offre = $_GET['id'] ?? null;
if (!$id_offre) {
    header("Location: dashboard_agriculteur.php");
    exit;
}

// Traitement de l'acceptation ou du refus
if (isset($_GET['action']) && isset($_GET['id_cand'])) {
    $action = ($_GET['action'] == 'accepter') ? 'Accepte' : 'Refuse';
    $id_cand = $_GET['id_cand'];

    try {
        $conn->beginTransaction();

        $stmt_check = $conn->prepare("SELECT o.nombre_ouvriers, 
                                      (SELECT COUNT(*) FROM candidature WHERE id_offre = o.id_offre AND decision = 'Accepte') as total_acceptes 
                                      FROM offre o 
                                      WHERE o.id_offre = ? 
                                      FOR UPDATE");
        $stmt_check->execute([$id_offre]);
        $offre_info = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($action == 'Accepte' && $offre_info['total_acceptes'] >= $offre_info['nombre_ouvriers']) {
            $conn->rollBack();
            $error = "offre complète";
        } else {
            $stmt_update = $conn->prepare("UPDATE candidature SET decision = ? WHERE id_candidature = ?");
            $stmt_update->execute([$action, $id_cand]);
            $conn->commit();
            header("Location: voir_postulants.php?id=" . $id_offre);
            exit;
        }
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Erreur lors du traitement : " . $e->getMessage();
    }
}

// Vérifier si le quota est atteint pour masquer le bouton "Accepter"
$stmt_quota = $conn->prepare("SELECT COUNT(*) FROM candidature WHERE id_offre = ? AND decision = 'Accepte'");
$stmt_quota->execute([$id_offre]);
$total_acceptes = $stmt_quota->fetchColumn();

$stmt_offre_info = $conn->prepare("SELECT nombre_ouvriers FROM offre WHERE id_offre = ?");
$stmt_offre_info->execute([$id_offre]);
$offre_max = $stmt_offre_info->fetchColumn();
$quota_atteint = ($total_acceptes >= $offre_max);

// Récupérer la liste des postulants avec leur moyenne de notes
$sql = "SELECT c.id_candidature, c.decision, o.id_ouvrier, o.nom, o.prenom, o.photo, o.description,
        (SELECT AVG(notr) FROM candidature WHERE id_ouvrier = o.id_ouvrier AND notr > 0) as moyenne_notes
        FROM candidature c
        JOIN ouvrier o ON c.id_ouvrier = o.id_ouvrier
        WHERE c.id_offre = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$id_offre]);
$postulants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Reload postulants after processing to show updated status
if (isset($_GET['action']) && isset($_GET['id_cand'])) {
    $stmt->execute([$id_offre]);
    $postulants = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer les commentaires passés de tous les postulants
$comments_map = [];
if (!empty($postulants)) {
    $ouvrier_ids = array_column($postulants, 'id_ouvrier');
    $placeholders = implode(',', array_fill(0, count($ouvrier_ids), '?'));
    // ✅ Corrigé : jointure propre + DISTINCT évitant les doublons par offre
    $stmt_comments = $conn->prepare("SELECT c.notr, c.commentaire, a.nom, a.prenom, c.id_ouvrier, o.id_offre
                                    FROM candidature c
                                    JOIN offre o ON c.id_offre = o.id_offre
                                    JOIN agriculteur a ON o.id_agriculteur = a.id_agriculteur
                                    WHERE c.id_ouvrier IN ($placeholders) AND c.notr > 0 AND c.id_offre != ?
                                    ORDER BY c.id_candidature DESC
                                    LIMIT 30");
    $params_comments = array_merge($ouvrier_ids, [$id_offre]);
    $stmt_comments->execute($params_comments);
    $all_comments = $stmt_comments->fetchAll(PDO::FETCH_ASSOC);
    $seen = [];
    foreach ($all_comments as $c) {
        // ✅ Corrigé : déduplication par ouvrier + commentaire uniquement (indépendamment de l'offre)
        $key = $c['id_ouvrier'] . '|' . strtolower(trim($c['commentaire']));
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $comments_map[$c['id_ouvrier']][] = $c;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Postulants - AgriConnect</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="dashboard_agriculteur.css?v=2">
    <style>
        .card-ouvrier { 
            background: var(--glass-bg); 
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            padding: 25px; 
            border-radius: 16px; 
            margin-bottom: 20px; 
            display: flex; 
            align-items: center; 
            gap: 25px; 
            transition: all 0.3s ease;
        }
        
        .card-ouvrier:hover {
            border-color: rgba(255, 215, 0, 0.2);
            box-shadow: var(--shadow);
            transform: translateY(-3px);
        }
        
        .photo-profil { 
            width: 90px; 
            height: 90px; 
            border-radius: 50%; 
            object-fit: cover; 
            border: 3px solid var(--accent-gold);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            background: rgba(0,0,0,0.2);
        }
        
        .status { 
            font-weight: 600; 
            padding: 6px 12px; 
            border-radius: 6px; 
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status.Accepte { 
            background: rgba(0, 200, 83, 0.15); 
            color: #00c853; 
            border: 1px solid rgba(0, 200, 83, 0.3);
        }
        
        .status.Refuse { 
            background: rgba(255, 82, 82, 0.15); 
            color: #ff5252; 
            border: 1px solid rgba(255, 82, 82, 0.3);
        }
        
        .status.Encours { 
            background: rgba(255, 167, 38, 0.15); 
            color: #ffa726; 
            border: 1px solid rgba(255, 167, 38, 0.3);
        }

        .ouvrier-info h3 {
            color: #fff;
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            margin-bottom: 8px;
        }

        .ouvrier-info p {
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.7;
            margin-bottom: 5px;
        }

        .ouvrier-info .note {
            color: var(--accent-gold);
            font-weight: 600;
        }

        .actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .actions .btn {
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }

        .empty-state h3 {
            color: #fff;
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

<header>
    <h1>🌿 Uber-Cueillette</h1>
    <nav>
        <a href="dashboard_agriculteur.php">Dashboard</a>
        <a href="logout.php">Déconnexion</a>
    </nav>
</header>

<div class="container">
    <section class="section-box">
        <h2 style="font-family: 'Playfair Display', serif; color: #fff; margin-bottom: 25px;"> Postulants pour l'offre #<?= htmlspecialchars($id_offre) ?></h2>
        
        <?php if(isset($error)): ?>
            <div class="msg-alert msg-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (empty($postulants)): ?>
            <div class="empty-state">
                <h3>Aucun postulant</h3>
                <p>Personne n'a encore postulé à cette offre. Revenez plus tard !</p>
            </div>
        <?php else: ?>
            <?php foreach ($postulants as $p): ?>
                <div class="card-ouvrier">
                    <?php if($p['photo']): ?>
                        <img src="data:image/jpeg;base64,<?= base64_encode($p['photo']) ?>" class="photo-profil">
                    <?php else: ?>
                        <div class="photo-profil" style="display:flex; align-items:center; justify-content:center; color: var(--text-muted); font-size: 12px;">Sans photo</div>
                    <?php endif; ?>

                    <div class="ouvrier-info" style="flex-grow: 1;">
                        <h3><?= htmlspecialchars($p['nom'] . " " . $p['prenom']) ?></h3>
                        <p class="note"> Note moyenne : <?= $p['moyenne_notes'] ? round($p['moyenne_notes'], 1) . "/10" : "Pas encore noté" ?></p>
                        <p><i>"<?= htmlspecialchars($p['description']) ?>"</i></p>
                        <p>Statut actuel : <span class="status <?= str_replace(' ', '', $p['decision']) ?>"><?= $p['decision'] ?></span></p>
                        
                        <?php if (isset($comments_map[$p['id_ouvrier']]) && !empty($comments_map[$p['id_ouvrier']])): ?>
                            <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--glass-border);">
                                <p style="color: var(--accent-gold); font-weight: 600; font-size: 13px; margin-bottom: 8px;">📝 Commentaires des agriculteurs :</p>
                                <?php foreach ($comments_map[$p['id_ouvrier']] as $comm): ?>
                                    <div style="background: rgba(0,0,0,0.15); border-radius: 8px; padding: 10px 14px; margin-bottom: 8px;">
                                        <p style="color: #fff; font-size: 13px; margin-bottom: 4px;"><b><?= htmlspecialchars($comm['nom'] . ' ' . $comm['prenom']) ?></b> — <span style="color: var(--accent-gold);"><?= $comm['notr'] ?>/10</span></p>
                                        <p style="color: var(--text-muted); font-size: 13px; font-style: italic;">"<?= htmlspecialchars($comm['commentaire']) ?>"</p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="actions">
                        <?php if ($p['decision'] == 'En cours' && !$quota_atteint): ?>
                            <a href="voir_postulants.php?id=<?= $id_offre ?>&id_cand=<?= $p['id_candidature'] ?>&action=accepter" 
                               onclick="return confirm('Accepter cet ouvrier ?')" class="btn"> Accepter</a>
                            <a href="voir_postulants.php?id=<?= $id_offre ?>&id_cand=<?= $p['id_candidature'] ?>&action=refuser" 
                               onclick="return confirm('Refuser cet ouvrier ?')" class="btn delete"> Refuser</a>
                        <?php elseif ($p['decision'] == 'En cours' && $quota_atteint): ?>
                            <span style="color: var(--text-muted); font-size: 12px;">offre complète</span>
                            <a href="voir_postulants.php?id=<?= $id_offre ?>&id_cand=<?= $p['id_candidature'] ?>&action=refuser" 
                               onclick="return confirm('Refuser cet ouvrier ?')" class="btn delete"> Refuser</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>

</body>
</html>
