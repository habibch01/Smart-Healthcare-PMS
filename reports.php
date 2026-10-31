<?php
require_once "config/database.php";

// Get projects
$projects = $pdo->query(
    "SELECT * FROM projects ORDER BY id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

// Get project summary counts
$summary = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM tasks) AS total_tasks,
        (SELECT COUNT(*) FROM tasks WHERE status = 'Completed') AS completed_tasks,
        (SELECT COUNT(*) FROM defects WHERE status NOT IN ('Resolved','Closed')) AS open_defects,
        (SELECT COUNT(*) FROM risks WHERE status != 'Closed') AS open_risks,
        (SELECT COUNT(*) FROM change_requests WHERE status = 'Pending') AS pending_changes,
        (SELECT COUNT(*) FROM requirements) AS total_requirements,
        (SELECT COUNT(*) FROM milestones WHERE status = 'Completed') AS completed_milestones,
        (SELECT COUNT(*) FROM milestones) AS total_milestones"
)->fetch(PDO::FETCH_ASSOC);

// Cost and Earned Value summary
$cost = $pdo->query(
    "SELECT
        COALESCE(SUM(tc.planned_cost), 0) AS pv,
        COALESCE(SUM(tc.earned_value), 0) AS ev,
        COALESCE(SUM(tc.actual_cost), 0) AS ac
     FROM task_costs tc"
)->fetch(PDO::FETCH_ASSOC);

$pv = (float)$cost["pv"];
$ev = (float)$cost["ev"];
$ac = (float)$cost["ac"];

$cpi = $ac > 0 ? $ev / $ac : null;
$spi = $pv > 0 ? $ev / $pv : null;

// Approved changes only
$approvedChanges = $pdo->query(
    "SELECT
        COALESCE(SUM(cost_impact), 0) AS extra_cost,
        COALESCE(SUM(schedule_impact_weeks), 0) AS extra_weeks
     FROM change_requests
     WHERE status = 'Approved'"
)->fetch(PDO::FETCH_ASSOC);

// Project closure records
$closures = $pdo->query(
    "SELECT pc.*, p.project_name
     FROM project_closure pc
     INNER JOIN projects p ON p.id = pc.project_id
     ORDER BY pc.id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

// Display helpers (UI only)
$totalTasks = (int)$summary["total_tasks"];
$completedTasks = (int)$summary["completed_tasks"];
$taskProgress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

$totalMilestones = (int)$summary["total_milestones"];
$completedMilestones = (int)$summary["completed_milestones"];
$milestoneProgress = $totalMilestones > 0
    ? round(($completedMilestones / $totalMilestones) * 100)
    : 0;

function indexPill($value)
{
    if ($value === null) {
        return '<span class="pill pill-muted">N/A</span>';
    }

    $class = $value >= 1 ? "pill-green" : "pill-red";
    $label = $value >= 1 ? "On track" : "Needs attention";

    return '<span class="pill ' . $class . '">' . $label . '</span>';
}

function projectStatusPill($status)
{
    $status = (string)$status;
    $lower = strtolower($status);

    if (in_array($lower, ["completed", "closed", "done"], true)) {
        $class = "pill-green";
    } elseif (in_array($lower, ["on hold", "cancelled", "delayed"], true)) {
        $class = "pill-red";
    } elseif (in_array($lower, ["in progress", "active", "ongoing"], true)) {
        $class = "pill-blue";
    } else {
        $class = "pill-orange";
    }

    return '<span class="pill ' . $class . '">'
        . htmlspecialchars($status === "" ? "Unknown" : $status)
        . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Project Reports | Smart Healthcare PMS</title>

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

        .section-heading {
            font-size: 16px;
            font-weight: 800;
            color: #26334d;
            margin-bottom: 15px;
        }

        /* Buttons */
        .btn-gradient {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(110deg, #6366f1, #8b5cf6);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 11px 22px;
            font-size: 13px;
            font-weight: 700;
            box-shadow: 0 6px 18px rgba(99, 102, 241, 0.3);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
        }

        .btn-gradient:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(99, 102, 241, 0.38);
        }

        /* Stat cards */
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

        .stat-card h3.money-lg {
            font-size: 24px;
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

        /* Detail rows */
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

        /* Table */
        .table-wrap {
            overflow-x: auto;
        }

        .pms-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
        }

        .pms-table thead th {
            background: #f6f7fd;
            color: var(--muted);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 13px 15px;
            border-bottom: 1px solid #edf0f7;
            white-space: nowrap;
            text-align: left;
        }

        .pms-table thead th:first-child {
            border-top-left-radius: 10px;
        }

        .pms-table thead th:last-child {
            border-top-right-radius: 10px;
        }

        .pms-table tbody td {
            padding: 15px;
            border-bottom: 1px solid #f0f2f8;
            vertical-align: middle;
            color: #293650;
        }

        .pms-table tbody tr:last-child td {
            border-bottom: none;
        }

        .pms-table tbody tr:hover td {
            background: #fafaff;
        }

        .cell-strong {
            font-weight: 700;
            color: #1f2b46;
        }

        .money {
            font-weight: 700;
            white-space: nowrap;
        }

        .cell-muted {
            color: var(--muted);
            font-size: 12px;
        }

        .project-chip {
            display: inline-block;
            background: #eeedff;
            color: #4f46e5;
            border-radius: 8px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 700;
        }

        .measure-tag {
            display: inline-block;
            min-width: 44px;
            text-align: center;
            background: #eeedff;
            color: #4f46e5;
            border-radius: 8px;
            padding: 4px 9px;
            font-size: 11px;
            font-weight: 800;
            margin-right: 8px;
        }

        /* Pills */
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 11px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .pill::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        .pill-green {
            background: #e8f9ef;
            color: #16834a;
        }

        .pill-red {
            background: #fee2e2;
            color: #b91c1c;
        }

        .pill-orange {
            background: #ffedd5;
            color: #c2410c;
        }

        .pill-blue {
            background: #eeedff;
            color: #4f46e5;
        }

        .pill-muted {
            background: #eef0f6;
            color: #6b7690;
        }

        .index-value {
            font-weight: 800;
            font-size: 15px;
            margin-right: 8px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 15px !important;
            color: var(--muted) !important;
        }

        .empty-state i {
            display: block;
            font-size: 30px;
            margin-bottom: 8px;
            color: #c5cbe0;
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

        /* Print / Save PDF */
        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: white;
            }

            .main-content {
                width: 100% !important;
                flex: 0 0 100% !important;
                max-width: 100% !important;
                padding: 0;
            }

            .panel,
            .stat-card {
                box-shadow: none !important;
                transform: none !important;
                border: 1px solid #ddd;
                break-inside: avoid;
            }

            .progress-bar,
            .pill,
            .stat-icon,
            .project-chip,
            .measure-tag,
            .pms-table thead th {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>

<div class="container-fluid">
    <div class="row">

        <!-- Sidebar -->
        <aside class="col-md-3 col-lg-2 sidebar no-print">

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

            <a href="dashboard.php">
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

            <a class="active" href="reports.php">
                <i class="bi bi-bar-chart-line"></i> Reports
            </a>

        </aside>

        <!-- Main Content -->
        <main class="col-md-9 col-lg-10 main-content">

            <!-- Header -->
            <div class="topbar">
                <div>
                    <h1 class="page-title">Project Reports</h1>
                    <p class="page-subtitle">
                        Project progress, cost performance, quality, and risk summary.
                    </p>
                </div>

                <button onclick="window.print()" class="btn-gradient no-print">
                    <i class="bi bi-printer"></i>
                    Print / Save PDF
                </button>
            </div>

            <!-- Overall Summary -->
            <h5 class="section-heading">Overall Project Summary</h5>

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
                        <div class="stat-note">Tasks across all projects</div>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-top">
                            <h6>Completed Tasks</h6>
                            <div class="stat-icon icon-green">
                                <i class="bi bi-check2-circle"></i>
                            </div>
                        </div>
                        <h3><?= $completedTasks ?></h3>
                        <div class="stat-note">Tasks marked completed</div>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-top">
                            <h6>Open Defects</h6>
                            <div class="stat-icon icon-red">
                                <i class="bi bi-bug"></i>
                            </div>
                        </div>
                        <h3><?= (int)$summary["open_defects"] ?></h3>
                        <div class="stat-note">Not yet resolved or closed</div>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-top">
                            <h6>Open Risks</h6>
                            <div class="stat-icon icon-orange">
                                <i class="bi bi-shield-exclamation"></i>
                            </div>
                        </div>
                        <h3><?= (int)$summary["open_risks"] ?></h3>
                        <div class="stat-note">Risks still being managed</div>
                    </div>
                </div>

            </div>

            <!-- Progress panels -->
            <div class="row g-3 mt-2">

                <div class="col-lg-6">
                    <section class="panel">

                        <h5 class="panel-title">
                            <i class="bi bi-graph-up-arrow text-primary me-2"></i>
                            Task Completion
                        </h5>

                        <p class="panel-subtitle">
                            Overall progress based on completed tasks.
                        </p>

                        <div class="progress-info">
                            <span class="progress-caption">Task progress</span>
                            <span class="progress-percent"><?= $taskProgress ?>%</span>
                        </div>

                        <div class="progress">
                            <div class="progress-bar"
                                 role="progressbar"
                                 style="width: <?= $taskProgress ?>%;"
                                 aria-valuenow="<?= $taskProgress ?>"
                                 aria-valuemin="0"
                                 aria-valuemax="100"></div>
                        </div>

                        <div class="progress-footer">
                            <span><?= $completedTasks ?> of <?= $totalTasks ?> tasks completed</span>
                            <span><?= $totalTasks - $completedTasks ?> remaining</span>
                        </div>

                    </section>
                </div>

                <div class="col-lg-6">
                    <section class="panel">

                        <h5 class="panel-title">
                            <i class="bi bi-flag text-primary me-2"></i>
                            Milestone Summary
                        </h5>

                        <p class="panel-subtitle">
                            Milestones reached so far.
                        </p>

                        <div class="progress-info">
                            <span class="progress-caption">Milestone progress</span>
                            <span class="progress-percent"><?= $milestoneProgress ?>%</span>
                        </div>

                        <div class="progress">
                            <div class="progress-bar"
                                 role="progressbar"
                                 style="width: <?= $milestoneProgress ?>%;"
                                 aria-valuenow="<?= $milestoneProgress ?>"
                                 aria-valuemin="0"
                                 aria-valuemax="100"></div>
                        </div>

                        <div class="progress-footer">
                            <span>
                                <?= $completedMilestones ?> out of <?= $totalMilestones ?> milestones completed
                            </span>
                            <span><?= $totalMilestones - $completedMilestones ?> remaining</span>
                        </div>

                    </section>
                </div>

            </div>

            <!-- Project Details -->
            <section class="panel mt-4">

                <h5 class="panel-title">
                    <i class="bi bi-folder2-open text-primary me-2"></i>
                    Project Details
                </h5>

                <p class="panel-subtitle">
                    Dates, budget, and current status of each project.
                </p>

                <div class="table-wrap">
                    <table class="pms-table">

                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Approved Budget</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($projects as $project): ?>
                                <tr>
                                    <td class="cell-strong">
                                        <?= htmlspecialchars($project["project_name"]) ?>
                                    </td>
                                    <td><?= htmlspecialchars($project["start_date"] ?? "") ?></td>
                                    <td><?= htmlspecialchars($project["end_date"] ?? "") ?></td>
                                    <td class="money">
                                        PKR <?= number_format((float)$project["approved_budget"], 2) ?>
                                    </td>
                                    <td><?= projectStatusPill($project["status"] ?? "") ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (!$projects): ?>
                                <tr>
                                    <td colspan="5" class="empty-state">
                                        <i class="bi bi-inbox"></i>
                                        No projects found.
                                    </td>
                                </tr>
                            <?php endif; ?>

                        </tbody>

                    </table>
                </div>

            </section>

            <!-- Cost Performance -->
            <section class="panel mt-4">

                <h5 class="panel-title">
                    <i class="bi bi-currency-rupee text-primary me-2"></i>
                    Earned Value Management (EVA / EVM)
                </h5>

                <p class="panel-subtitle">
                    Cost and schedule performance across all tasks.
                </p>

                <div class="table-wrap">
                    <table class="pms-table">

                        <thead>
                            <tr>
                                <th>Measure</th>
                                <th>Value</th>
                                <th>Meaning</th>
                            </tr>
                        </thead>

                        <tbody>

                            <tr>
                                <td><span class="measure-tag">PV</span> <span class="cell-strong">Planned Value</span></td>
                                <td class="money">PKR <?= number_format($pv, 2) ?></td>
                                <td class="cell-muted">Budgeted value of planned work</td>
                            </tr>

                            <tr>
                                <td><span class="measure-tag">EV</span> <span class="cell-strong">Earned Value</span></td>
                                <td class="money">PKR <?= number_format($ev, 2) ?></td>
                                <td class="cell-muted">Budgeted value of completed work</td>
                            </tr>

                            <tr>
                                <td><span class="measure-tag">AC</span> <span class="cell-strong">Actual Cost</span></td>
                                <td class="money">PKR <?= number_format($ac, 2) ?></td>
                                <td class="cell-muted">Actual amount spent</td>
                            </tr>

                            <tr>
                                <td><span class="measure-tag">CPI</span> <span class="cell-strong">Cost Performance Index</span></td>
                                <td>
                                    <span class="index-value">
                                        <?= $cpi === null ? "N/A" : number_format($cpi, 2) ?>
                                    </span>
                                    <?= indexPill($cpi) ?>
                                </td>
                                <td class="cell-muted">EV / AC; above 1 indicates cost efficiency</td>
                            </tr>

                            <tr>
                                <td><span class="measure-tag">SPI</span> <span class="cell-strong">Schedule Performance Index</span></td>
                                <td>
                                    <span class="index-value">
                                        <?= $spi === null ? "N/A" : number_format($spi, 2) ?>
                                    </span>
                                    <?= indexPill($spi) ?>
                                </td>
                                <td class="cell-muted">EV / PV; above 1 indicates schedule performance ahead of plan</td>
                            </tr>

                        </tbody>

                    </table>
                </div>

            </section>

            <!-- Requirements and Change Requests -->
            <h5 class="section-heading mt-4">Requirements and Change Requests</h5>

            <div class="row g-3">

                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-top">
                            <h6>Total Requirements</h6>
                            <div class="stat-icon icon-blue">
                                <i class="bi bi-card-checklist"></i>
                            </div>
                        </div>
                        <h3><?= (int)$summary["total_requirements"] ?></h3>
                        <div class="stat-note">Recorded project requirements</div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-top">
                            <h6>Pending Change Requests</h6>
                            <div class="stat-icon icon-orange">
                                <i class="bi bi-arrow-repeat"></i>
                            </div>
                        </div>
                        <h3><?= (int)$summary["pending_changes"] ?></h3>
                        <div class="stat-note">Awaiting approval</div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-top">
                            <h6>Approved Extra Cost</h6>
                            <div class="stat-icon icon-green">
                                <i class="bi bi-cash-coin"></i>
                            </div>
                        </div>
                        <h3 class="money-lg">
                            PKR <?= number_format((float)$approvedChanges["extra_cost"], 2) ?>
                        </h3>
                        <div class="stat-note">
                            Approved extra duration:
                            <strong><?= (int)$approvedChanges["extra_weeks"] ?> weeks</strong>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Project Closure -->
            <section class="panel mt-4">

                <h5 class="panel-title">
                    <i class="bi bi-archive text-primary me-2"></i>
                    Project Closure Records
                </h5>

                <p class="panel-subtitle">
                    Final status and lessons learned for closed projects.
                </p>

                <div class="table-wrap">
                    <table class="pms-table">

                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Closure Date</th>
                                <th>Final Status</th>
                                <th>Approved By</th>
                                <th>Lessons Learned</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($closures as $closure): ?>
                                <tr>
                                    <td>
                                        <span class="project-chip">
                                            <?= htmlspecialchars($closure["project_name"]) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($closure["closure_date"] ?? "") ?></td>
                                    <td><?= htmlspecialchars($closure["final_status"] ?? "") ?></td>
                                    <td><?= htmlspecialchars($closure["approved_by"] ?? "") ?></td>
                                    <td class="cell-muted">
                                        <?= htmlspecialchars($closure["lessons_learned"] ?? "") ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (!$closures): ?>
                                <tr>
                                    <td colspan="5" class="empty-state">
                                        <i class="bi bi-inbox"></i>
                                        No project closure records yet.
                                    </td>
                                </tr>
                            <?php endif; ?>

                        </tbody>

                    </table>
                </div>

            </section>

            <!-- Footer -->
            <div class="footer">
                Smart Healthcare PMS &nbsp;•&nbsp; Project Monitoring Dashboard
            </div>

        </main>

    </div>
</div>

</body>
</html>