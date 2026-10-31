```php
<?php
require_once "config/database.php";

// Get latest project
$stmt = $pdo->query("SELECT * FROM projects ORDER BY id DESC LIMIT 1");
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    die("No project found. Please add a project first.");
}

$projectId = $project['id'];

// Total tasks
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE project_id = ?");
$stmt->execute([$projectId]);
$totalTasks = (int) $stmt->fetchColumn();

// Completed tasks
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM tasks
     WHERE project_id = ? AND status = 'Completed'"
);
$stmt->execute([$projectId]);
$completedTasks = (int) $stmt->fetchColumn();

// Critical unresolved defects
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM defects
     WHERE project_id = ?
     AND severity = 'Critical'
     AND status NOT IN ('Resolved', 'Closed')"
);
$stmt->execute([$projectId]);
$criticalDefects = (int) $stmt->fetchColumn();

// Pending change requests
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM change_requests
     WHERE project_id = ? AND status = 'Pending'"
);
$stmt->execute([$projectId]);
$pendingChanges = (int) $stmt->fetchColumn();

// Task progress
$progress = $totalTasks > 0
    ? round(($completedTasks / $totalTasks) * 100)
    : 0;

// Budget
$budget = number_format((float) $project['approved_budget'], 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | Smart Healthcare PMS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">

    <style>
        :root {
            --primary: #6366f1;
            --purple: #8b5cf6;
            --navy: #111b2e;
            --background: #f5f7ff;
            --text: #202b46;
            --muted: #8490a5;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background:
                radial-gradient(circle at top right, #e8eaff 0, transparent 28%),
                var(--background);
            font-family: "Segoe UI", Arial, sans-serif;
            color: var(--text);
        }

        /* Sidebar */
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #111827, #192b49);
            padding: 28px 15px;
            box-shadow: 5px 0 25px rgba(15, 23, 42, 0.08);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 11px;
            color: white;
            font-size: 17px;
            font-weight: 800;
            line-height: 1.4;
            margin: 0 5px 30px;
        }

        .brand-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 43px;
            height: 43px;
            border-radius: 13px;
            background: linear-gradient(135deg, #6366f1, #a855f7);
            font-size: 23px;
            box-shadow: 0 6px 18px rgba(99, 102, 241, 0.3);
        }

        .menu-heading {
            color: #71829e;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 18px 12px 9px;
            letter-spacing: 1.5px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 11px;
            color: #aebbd0;
            text-decoration: none;
            padding: 12px 13px;
            border-radius: 10px;
            margin-bottom: 5px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid transparent;
            transition: all 0.25s ease;
        }

        .sidebar a i {
            font-size: 16px;
        }

        .sidebar a:hover {
            background: rgba(255, 255, 255, 0.08);
            color: white;
            transform: translateX(3px);
        }

        .sidebar a.active {
            background: linear-gradient(110deg, #6366f1, #8b5cf6);
            color: white;
            box-shadow: 0 6px 18px rgba(99, 102, 241, 0.3);
        }

        /* Main content */
        .main-content {
            padding: 32px;
            min-width: 0;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.8px;
            color: #17213a;
            margin: 0 0 6px;
        }

        .page-subtitle {
            color: var(--muted);
            font-size: 13px;
            margin: 0;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 13px;
            border-radius: 30px;
            background: #e8f9ef;
            color: #16834a;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-dot {
            height: 7px;
            width: 7px;
            background: #22c55e;
            border-radius: 50%;
        }

        /* Project banner */
        .project-banner {
            position: relative;
            overflow: hidden;
            background: linear-gradient(115deg, #3730a3, #6366f1 65%, #8b5cf6);
            color: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 12px 30px rgba(99, 102, 241, 0.2);
            margin-bottom: 25px;
        }

        .project-banner::after {
            content: "";
            position: absolute;
            width: 240px;
            height: 240px;
            right: -65px;
            top: -100px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.09);
        }

        .project-banner::before {
            content: "";
            position: absolute;
            width: 150px;
            height: 150px;
            right: 100px;
            bottom: -110px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
        }

        .banner-content {
            position: relative;
            z-index: 1;
        }

        .banner-label {
            font-size: 11px;
            font-weight: 750;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #dddfff;
            margin-bottom: 12px;
        }

        .project-banner h3 {
            font-size: 23px;
            font-weight: 800;
            margin-bottom: 10px;
            line-height: 1.4;
        }

        .project-banner p {
            color: #e4e7ff;
            font-size: 13px;
            margin-bottom: 22px;
            max-width: 650px;
        }

        .banner-details {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .banner-detail {
            background: rgba(255, 255, 255, 0.13);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 10px;
            padding: 10px 13px;
            font-size: 12px;
        }

        .banner-detail span {
            color: #dedfff;
            display: block;
            font-size: 10px;
            margin-bottom: 4px;
        }

        .banner-detail strong {
            font-size: 13px;
            font-weight: 700;
        }

        /* Statistic cards */
        .section-heading {
            font-size: 16px;
            font-weight: 800;
            color: #26334d;
            margin-bottom: 15px;
        }

        .stat-card {
            position: relative;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.94);
            border: 1px solid #edf0f7;
            border-radius: 17px;
            padding: 21px;
            height: 100%;
            box-shadow: 0 6px 24px rgba(31, 41, 55, 0.04);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .stat-card:hover,
        .panel:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 32px rgba(31, 41, 55, 0.08);
        }

        .stat-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .stat-card h6 {
            color: var(--muted);
            font-size: 11px;
            font-weight: 750;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }

        .stat-icon {
            width: 43px;
            height: 43px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 13px;
            font-size: 21px;
        }

        .icon-blue {
            color: #4f46e5;
            background: #eeedff;
        }

        .icon-green {
            color: #059669;
            background: #dcfce7;
        }

        .icon-orange {
            color: #ea580c;
            background: #ffedd5;
        }

        .icon-red {
            color: #dc2626;
            background: #fee2e2;
        }

        .stat-card h3 {
            font-size: 32px;
            font-weight: 850;
            color: #202b46;
            margin: 18px 0 5px;
            letter-spacing: -1px;
        }

        .stat-note {
            color: var(--muted);
            font-size: 11px;
        }

        /* Panels */
        .panel {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid #edf0f7;
            border-radius: 17px;
            padding: 24px;
            height: 100%;
            box-shadow: 0 6px 24px rgba(31, 41, 55, 0.04);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .panel-title {
            font-size: 15px;
            font-weight: 800;
            color: #26334d;
            margin: 0;
        }

        .panel-subtitle {
            color: var(--muted);
            font-size: 12px;
            margin: 7px 0 20px;
        }

        /* Progress */
        .progress {
            height: 11px;
            background: #eceeff;
            border-radius: 20px;
            overflow: hidden;
        }

        .progress-bar {
            background: linear-gradient(90deg, #6366f1, #a855f7);
            border-radius: 20px;
            transition: width 0.6s ease;
        }

        .progress-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .progress-percent {
            color: #6366f1;
            font-size: 22px;
            font-weight: 850;
        }

        .progress-caption {
            color: var(--muted);
            font-size: 12px;
        }

        .progress-footer {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 13px;
            color: var(--muted);
            font-size: 11px;
        }

        /* Overview details */
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding: 13px 0;
            border-bottom: 1px solid #f0f2f8;
            font-size: 12px;
        }

        .detail-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .detail-label {
            color: var(--muted);
        }

        .detail-value {
            color: #293650;
            font-weight: 700;
            text-align: right;
        }

        .footer {
            text-align: center;
            color: #9aa4b6;
            font-size: 11px;
            padding: 25px 0 5px;
        }

        @media (max-width: 767px) {
            .sidebar {
                min-height: auto;
                padding: 20px 14px;
            }

            .brand {
                margin-bottom: 18px;
            }

            .main-content {
                padding: 20px 14px;
            }

            .page-title {
                font-size: 23px;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .project-banner {
                padding: 23px;
            }

            .project-banner h3 {
                font-size: 19px;
            }

            .panel {
                padding: 20px;
            }

            .stat-card {
                padding: 17px;
            }

            .stat-card h3 {
                font-size: 27px;
            }
        }
    </style>
</head>

<body>

<div class="container-fluid">
    <div class="row">

        <!-- Sidebar -->
        <aside class="col-md-3 col-lg-2 sidebar">

            <div class="brand">
                <div class="brand-icon">
                    <i class="bi bi-heart-pulse-fill"></i>
                </div>
                <div>
                    Smart Healthcare
                    <div style="font-size: 11px; color: #9eacc4; font-weight: 500;">
                        Project Management
                    </div>
                </div>
            </div>

            <a class="active" href="dashboard.php">
                <i class="bi bi-grid-1x2-fill"></i> Dashboard
            </a>

            <div class="menu-heading">Project Planning</div>

            <a href="projects.php">
                <i class="bi bi-folder2-open"></i> Project Initiation
            </a>

            <a href="requirements.php">
                <i class="bi bi-card-checklist"></i> Scope & Requirements
            </a>

            <a href="wbs.php">
                <i class="bi bi-diagram-3"></i> Scope & WBS
            </a>

            <a href="schedule.php">
                <i class="bi bi-calendar3"></i> Schedule & Gantt
            </a>

            <a href="team.php">
                <i class="bi bi-people"></i> Team & Resources
            </a>

            <div class="menu-heading">Project Control</div>

            <a href="costs.php">
                <i class="bi bi-currency-rupee"></i> Cost Management
            </a>

            <a href="quality.php">
                <i class="bi bi-patch-check"></i> Quality & Defects
            </a>

            <a href="risks.php">
                <i class="bi bi-shield-exclamation"></i> Risk Management
            </a>

            <a href="change_requests.php">
                <i class="bi bi-arrow-left-right"></i> Change Requests
            </a>

            <div class="menu-heading">Monitoring</div>

            <a href="reports.php">
                <i class="bi bi-bar-chart-line"></i> Reports
            </a>

        </aside>

        <!-- Main Content -->
        <main class="col-md-9 col-lg-10 main-content">

            <!-- Header -->
            <div class="topbar">
                <div>
                    <h1 class="page-title">Project Dashboard</h1>
                    <p class="page-subtitle">
                        Welcome back! Here's your project overview.
                    </p>
                </div>

                <div class="status-badge">
                    <span class="status-dot"></span>
                    <?= htmlspecialchars($project['status'] ?? 'Unknown') ?>
                </div>
            </div>

            <!-- Project Banner -->
            <section class="project-banner">
                <div class="banner-content">

                    <div class="banner-label">
                        <i class="bi bi-stars"></i>
                        Current Project
                    </div>

                    <h3>
                        <?= htmlspecialchars($project['project_name']) ?>
                    </h3>

                    <p>
                        <?= htmlspecialchars($project['description'] ?? '') ?>
                    </p>

                    <div class="banner-details">

                        <div class="banner-detail">
                            <span><i class="bi bi-calendar-event"></i> START DATE</span>
                            <strong>
                                <?= htmlspecialchars($project['start_date'] ?? 'Not set') ?>
                            </strong>
                        </div>

                        <div class="banner-detail">
                            <span><i class="bi bi-calendar-check"></i> END DATE</span>
                            <strong>
                                <?= htmlspecialchars($project['end_date'] ?? 'Not set') ?>
                            </strong>
                        </div>

                        <div class="banner-detail">
                            <span><i class="bi bi-wallet2"></i> APPROVED BUDGET</span>
                            <strong>PKR <?= $budget ?></strong>
                        </div>

                    </div>
                </div>
            </section>

            <!-- Statistics -->
            <h5 class="section-heading">Project at a Glance</h5>

            <div class="row g-3">

                <div class="col-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-top">
                            <h6>Total Tasks</h6>
                            <div class="stat-icon icon-blue">
                                <i class="bi bi-list-task"></i>
                            </div>
                        </div>
                        <h3><?= $totalTasks ?></h3>
                        <div class="stat-note">Tasks in this project</div>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-top">
                            <h6>Completed</h6>
                            <div class="stat-icon icon-green">
                                <i class="bi bi-check2-circle"></i>
                            </div>
                        </div>
                        <h3><?= $completedTasks ?></h3>
                        <div class="stat-note">Tasks completed</div>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-top">
                            <h6>Pending Changes</h6>
                            <div class="stat-icon icon-orange">
                                <i class="bi bi-arrow-repeat"></i>
                            </div>
                        </div>
                        <h3><?= $pendingChanges ?></h3>
                        <div class="stat-note">Awaiting approval</div>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-top">
                            <h6>Critical Defects</h6>
                            <div class="stat-icon icon-red">
                                <i class="bi bi-exclamation-triangle"></i>
                            </div>
                        </div>
                        <h3><?= $criticalDefects ?></h3>
                        <div class="stat-note">Unresolved critical issues</div>
                    </div>
                </div>

            </div>

            <!-- Progress and Details -->
            <div class="row g-3 mt-2">

                <div class="col-lg-7">
                    <section class="panel">

                        <h5 class="panel-title">
                            <i class="bi bi-graph-up-arrow text-primary me-2"></i>
                            Task Completion
                        </h5>

                        <p class="panel-subtitle">
                            Overall progress based on completed tasks.
                        </p>

                        <div class="progress-info">
                            <span class="progress-caption">Project progress</span>
                            <span class="progress-percent"><?= $progress ?>%</span>
                        </div>

                        <div class="progress">
                            <div
                                class="progress-bar"
                                role="progressbar"
                                style="width: <?= $progress ?>%;"
                                aria-valuenow="<?= $progress ?>"
                                aria-valuemin="0"
                                aria-valuemax="100">
                            </div>
                        </div>

                        <div class="progress-footer">
                            <span>
                                <?= $completedTasks ?> of <?= $totalTasks ?> tasks completed
                            </span>
                            <span>
                                <?= $totalTasks - $completedTasks ?> remaining
                            </span>
                        </div>

                    </section>
                </div>

                <div class="col-lg-5">
                    <section class="panel">

                        <h5 class="panel-title">
                            <i class="bi bi-info-circle text-primary me-2"></i>
                            Project Overview
                        </h5>

                        <p class="panel-subtitle">
                            Key project information.
                        </p>

                        <div class="detail-row">
                            <span class="detail-label">Project Status</span>
                            <span class="detail-value">
                                <?= htmlspecialchars($project['status'] ?? 'Not set') ?>
                            </span>
                        </div>

                        <div class="detail-row">
                            <span class="detail-label">Approved Budget</span>
                            <span class="detail-value">PKR <?= $budget ?></span>
                        </div>

                        <div class="detail-row">
                            <span class="detail-label">Total Tasks</span>
                            <span class="detail-value"><?= $totalTasks ?></span>
                        </div>

                        <div class="detail-row">
                            <span class="detail-label">Completion</span>
                            <span class="detail-value"><?= $progress ?>%</span>
                        </div>

                    </section>
                </div>

            </div>

            <!-- Footer -->
            <div class="footer">
                Smart Healthcare PMS &nbsp;•&nbsp; Project Monitoring Dashboard
            </div>

        </main>

    </div>
</div>

</body>
</html>
```