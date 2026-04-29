<?php
session_start();
require_once("config.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== "agriculteur") {
    header("Location: login.php");
    exit;
}

$id_agri = $_SESSION['user_id'];
$id_offre = $_GET['id'] ?? null;

if (!$id_offre) {
    header("Location: dashboard_agriculteur.php");
    exit;
}

// Vérifier que l'offre appartient à l'agriculteur et est terminée (par date ou par quota)
$stmt_offre = $conn->prepare("SELECT o.*, f.libelle as fruit, g.libelle as gouv,
                             (SELECT COUNT(*) FROM candidature WHERE id_offre = o.id_offre AND decision = 'Accepte') as total_acceptes
                             FROM offre o 
                             JOIN type_fruit f ON o.id_type_fruit = f.id_type_fruit
                             JOIN gouvernorat g ON o.id_gouvernorat = g.id_gouvernorat
                             WHERE o.id_offre = ? AND o.id_agriculteur = ?");
$stmt_offre->execute([$id_offre, $id_agri]);
$offre = $stmt_offre->fetch(PDO::FETCH_ASSOC);

$offre_terminee = ($offre && ($offre['date_fin'] < date('Y-m-d') || $offre['total_acceptes'] >= $offre['nombre_ouvriers']));

if (!$offre_terminee) {
    header("Location: dashboard_agriculteur.php");
    exit;
}

// Récupérer les ouvriers acceptés pour cette offre
$stmt_ouvriers = $conn->prepare("SELECT c.id_candidature, c.notr, c.commentaire, c.remuneration, o.id_ouvrier, o.nom, o.prenom, o.photo
                                FROM candidature c
                                JOIN ouvrier o ON c.id_ouvrier = o.id_ouvrier
                                WHERE c.id_offre = ? AND c.decision = 'Accepte'");
$stmt_ouvriers->execute([$id_offre]);
$ouvriers = $stmt_ouvriers->fetchAll(PDO::FETCH_ASSOC);

$msg = "";
$error = "";

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success_count = 0;
    
    foreach ($ouvriers as $ouv) {
        $id_ouvrier = $ouv['id_ouvrier'];
        $notr = isset($_POST['notr_' . $id_ouvrier]) ? (int)$_POST['notr_' . $id_ouvrier] : 0;
        $commentaire = isset($_POST['commentaire_' . $id_ouvrier]) ? trim($_POST['commentaire_' . $id_ouvrier]) : '';
        $remuneration = isset($_POST['remuneration_' . $id_ouvrier]) ? (float)$_POST['remuneration_' . $id_ouvrier] : 0;
        
        if ($notr >= 0 && $notr <= 10) {
            $stmt_update = $conn->prepare("UPDATE candidature SET notr = ?, commentaire = ?, remuneration = ? WHERE id_ouvrier = ? AND id_offre = ?");
            $stmt_update->execute([$notr, $commentaire, $remuneration, $id_ouvrier, $id_offre]);
            $success_count++;
        }
    }
    
    if ($success_count > 0) {
        $msg = " Évaluations enregistrées avec succès !";
        // Recharger les données
        $stmt_ouvriers->execute([$id_offre]);
        $ouvriers = $stmt_ouvriers->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error = " Aucune évaluation n'a pu être enregistrée.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Évaluer les ouvriers - AgriConnect</title>
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
            align-items: flex-start;
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
            flex-shrink: 0;
        }
        
        .ouvrier-info {
            flex-grow: 1;
        }
        
        .ouvrier-info h3 {
            color: #fff;
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            margin-bottom: 15px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-group label {
            color: var(--accent-gold);
            font-size: 13px;
            font-weight: 600;
        }
        
        .form-group input,
        .form-group textarea {
            padding: 12px;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            color: var(--text-light);
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            outline: none;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            border-color: var(--accent-gold);
            box-shadow: 0 0 10px rgba(255, 215, 0, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .evaluated-badge {
            display: inline-block;
            background: rgba(0, 200, 83, 0.15);
            color: var(--success);
            border: 1px solid rgba(0, 200, 83, 0.3);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .offre-summary {
            background: rgba(0, 0, 0, 0.15);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .offre-summary h3 {
            color: var(--accent-gold);
            font-family: 'Playfair Display', serif;
            margin-bottom: 10px;
        }
        
        .offre-summary p {
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .btn-submit {
            padding: 14px 30px;
            border: none;
            background: linear-gradient(135deg, var(--accent-gold), var(--accent-gold-hover));
            color: var(--primary-dark);
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s ease;
            margin-top: 20px;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3);
        }
        
        @media (max-width: 768px) {
            .card-ouvrier {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-group.full-width {
                grid-column: 1;
            }
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
        <h2 style="font-family: 'Playfair Display', serif; color: #fff; margin-bottom: 25px;"> Évaluer les ouvriers</h2>
        
        <?php if($msg): ?>
            <div class="msg-alert msg-success"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="msg-alert msg-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="offre-summary">
            <h3><?= htmlspecialchars($offre['fruit']) ?> — <?= htmlspecialchars($offre['gouv']) ?></h3>
            <p> <?= htmlspecialchars($offre['adresse']) ?></p>
            <p> Du <?= date('d/m/Y', strtotime($offre['date_debut'])) ?> au <?= date('d/m/Y', strtotime($offre['date_fin'])) ?></p>
            <p> <?= number_format($offre['prix_journee'], 2) ?> DT / jour</p>
        </div>
        
        <?php if (empty($ouvriers)): ?>
            <div class="empty-state" style="text-align: center; padding: 40px; color: var(--text-muted);">
                <h3 style="color: #fff; font-family: 'Playfair Display', serif; margin-bottom: 15px;">Aucun ouvrier accepté</h3>
                <p>Vous n'avez accepté aucun ouvrier pour cette offre.</p>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <?php foreach ($ouvriers as $ouv): 
                    $est_evalue = ($ouv['notr'] > 0);
                ?>
                    <div class="card-ouvrier">
                        <?php if($ouv['photo']): ?>
                            <img src="data:image/jpeg;base64,<?= base64_encode($ouv['photo']) ?>" class="photo-profil">
                        <?php else: ?>
                            <div class="photo-profil" style="display:flex; align-items:center; justify-content:center; color: var(--text-muted); font-size: 12px;">Sans photo</div>
                        <?php endif; ?>
                        
                        <div class="ouvrier-info">
                            <h3><?= htmlspecialchars($ouv['nom'] . " " . $ouv['prenom']) ?></h3>
                            
                            <?php if ($est_evalue): ?>
                                <span class="evaluated-badge"> Déjà évalué</span>
                            <?php endif; ?>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="notr_<?= $ouv['id_ouvrier'] ?>">Note (0-10)</label>
                                    <input type="number" 
                                           id="notr_<?= $ouv['id_ouvrier'] ?>"
                                           name="notr_<?= $ouv['id_ouvrier'] ?>" 
                                           min="0" max="10" step="1"
                                           value="<?= $est_evalue ? htmlspecialchars($ouv['notr']) : '' ?>"
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="remuneration_<?= $ouv['id_ouvrier'] ?>">Rémunération (DT)</label>
                                    <input type="number" 
                                           id="remuneration_<?= $ouv['id_ouvrier'] ?>"
                                           name="remuneration_<?= $ouv['id_ouvrier'] ?>" 
                                           min="0" step="0.01"
                                           value="<?= $est_evalue ? htmlspecialchars($ouv['remuneration']) : '' ?>"
                                           required>
                                </div>
                                
                                <div class="form-group full-width">
                                    <label for="commentaire_<?= $ouv['id_ouvrier'] ?>">Commentaire sur la qualité du travail</label>
                                    <textarea 
                                        id="commentaire_<?= $ouv['id_ouvrier'] ?>"
                                        name="commentaire_<?= $ouv['id_ouvrier'] ?>" 
                                        placeholder="Décrivez la qualité du travail de cet ouvrier..."
                                        maxlength="255"><?= $est_evalue ? htmlspecialchars($ouv['commentaire']) : '' ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div style="text-align: center;">
                    <button type="submit" class="btn-submit"> Enregistrer les évaluations</button>
                </div>
            </form>
        <?php endif; ?>
        
        <p style="text-align: center; margin-top: 30px;">
            <a href="dashboard_agriculteur.php" style="color: var(--accent-gold); text-decoration: none; font-weight: 600;">← Retour au Dashboard</a>
        </p>
    </section>
</div>

</body>
</html>

