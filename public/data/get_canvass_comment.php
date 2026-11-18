<?php
require_once '../includes/db.php';

$pr_number = $_GET['pr_number'];

$stmt = $pdo->prepare("SELECT r.*, u.full_name, u.user_pfp, u.department 
                       FROM recommendations_suggestions r
                       JOIN users u ON r.user_id = u.user_id
                       WHERE r.pr_number = ?
                       ORDER BY r.created_date ASC");
$stmt->execute([$pr_number]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($comments as $comment): ?>
                <div class="card mb-3 shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-start mb-2">
                            <img src="<?php echo htmlspecialchars($comment['user_pfp']); ?>"
                                alt="User image"
                                class="rounded-circle me-2"
                                style="width: 40px; height: 40px; object-fit: cover;">

                            <div class="flex-grow-1">
                                <div class="fw-bold">
                                    <?php echo htmlspecialchars($comment['full_name']); ?>
                                </div>
                                <small class="text-muted d-block">
                                    <?php echo htmlspecialchars($comment['department']); ?> •
                                    <?php echo date('M d, Y h:i A', strtotime($comment['created_date'])); ?>
                                </small>
                                <p class="mt-2 mb-0">
                                    <?php echo nl2br(htmlspecialchars($comment['comments'])); ?>
                                </p>
                            </div>

                            <?php if ($_SESSION['user_id'] == $comment['user_id']): ?>
                                <div class="dropdown ms-2">
                                    <a href="#" class="text-secondary" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="material-icons-two-tone">more_vert</i>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="#">Edit</a></li>
                                        <li><a class="dropdown-item" href="#">Delete</a></li>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
