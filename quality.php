<?php
require_once "config/database.php";

$message = "";
$messageType = "success";

$projectsStmt = $pdo->query(
    "SELECT id, project_name FROM projects ORDER BY id DESC"
);
$projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);

$membersStmt = $pdo->query(
    "SELECT id, project_id, name, role
     FROM team_members
     ORDER BY name"
);
$members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

// Add a defect or save a quality check
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $formType = $_POST["form_type"] ?? "";
    $projectId = (int)($_POST["project_id"] ?? 0);

    $validProject = false;

    foreach ($projects as $project) {
        if ((int)$project["id"] === $projectId) {
            $validProject = true;
            break;
        }
    }

    if (!$validProject) {
        $message = "Please select a valid project.";
        $messageType = "error";
    } elseif ($formType === "defect") {
        $title = trim($_POST["title"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $severity = $_POST["severity"] ?? "Medium";
        $assignedTo = (int)($_POST["assigned_to"] ?? 0);

        $validSeverities = ["Low", "Medium", "High", "Critical"];
        $validMember = ($assignedTo === 0);

        foreach ($members as $member) {
            if (
                (int)$member["id"] === $assignedTo &&
                (int)$member["project_id"] === $projectId
            ) {
                $validMember = true;
                break;
            }
        }

        if (
            $title === "" ||
            strlen($title) > 200 ||
            !in_array($severity, $validSeverities, true) ||
            !$validMember
        ) {
            $message = "Please enter valid defect details.";
            $messageType = "error";
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO defects
                 (project_id, title, description, severity, assigned_to)
                 VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $projectId,
                $title,
                $description,
                $severity,
                $assignedTo === 0 ? null : $assignedTo
            ]);

            $message = "Defect added successfully!";
        }
    } elseif ($formType === "quality") {
        $checkName = trim($_POST["check_name"] ?? "");
        $result = $_POST["result"] ?? "Pending";
        $notes = trim($_POST["notes"] ?? "");
        $checkedAt = $_POST["checked_at"] ?? "";

        $validResults = ["Pending", "Pass", "Fail"];

        if (
            $checkName === "" ||
            strlen($checkName) > 200 ||
            !in_array($result, $validResults, true) ||
            ($checkedAt !== "" &&
             !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkedAt))
        ) {
            $message = "Please enter valid quality check details.";
            $messageType = "error";
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO quality_checks
                 (project_id, check_name, result, notes, checked_at)
                 VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $projectId,
                $checkName,
                $result,
                $notes,
                $checkedAt !== "" ? $checkedAt : null
            ]);

            $message = "Quality check saved successfully!";
        }
    } else {
        $message = "Invalid form submission.";
        $messageType = "error";
    }
}

// Get defects
$defectsStmt = $pdo->query(
    "SELECT d.*, p.project_name, tm.name AS member_name
     FROM defects d
     INNER JOIN projects p ON p.id = d.project_id
     LEFT JOIN team_members tm ON tm.id = d.assigned_to
     ORDER BY d.id DESC"
);
$defects = $defectsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get quality checks
$checksStmt = $pdo->query(
    "SELECT q.*, p.project_name
     FROM quality_checks q
     INNER JOIN projects p ON p.id = q.project_id
     ORDER BY q.id DESC"
);
$checks = $checksStmt->fetchAll(PDO::FETCH_ASSOC);

// Display summary and helpers (UI only)
$openDefects = 0;
$criticalOpen = 0;

foreach ($defects as $d) {
    $isOpen = !in_array($d["status"], ["Resolved", "Closed"], true);

    if ($isOpen) {
        $openDefects++;

        if ($d["severity"] === "Critical") {
            $criticalOpen++;
        }
    }
}

$passedChecks = 0;
$failedChecks = 0;

foreach ($checks as $c) {
    if ($c["result"] === "Pass") {
        $passedChecks++;
    } elseif ($c["result"] === "Fail") {
        $failedChecks++;
    }
}

function severityPill($severity)
{
    $map = [
        "Critical" => "pill-red",
        "High"     => "pill-orange",
        "Medium"   => "pill-blue",
        "Low"      => "pill-green",
    ];

    $class = $map[$severity] ?? "pill-muted";

    return '<span class="pill ' . $class . '">'
        . htmlspecialchars((string)$severity) . '</span>';
}

function defectStatusPill($status)
{
    $lower = strtolower((string)$status);

    if (in_array($lower, ["resolved", "closed"], true)) {
        $class = "pill-green";
    } elseif ($lower === "in progress") {
        $class = "pill-blue";
    } else {
        $class = "pill-orange";
    }

    return '<span class="pill ' . $class . '">'
        . htmlspecialchars((string)$status) . '</span>';
}

function resultPill($result)
{
    $map = [
        "Pass"    => "pill-green",
        "Fail"    => "pill-red",
        "Pending" => "pill-orange",
    ];

    $class = $map[$result] ?? "pill-muted";

    return '<span class="pill ' . $class . '">'
        . htmlspecialchars((string)$result) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Quality & Defects | Smart Healthcare PMS</title>

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

        .section-heading {
            font-size: 16px;
            font-weight: 800;
            color: #26334d;
            margin-bottom: 15px;
        }

        /* Alerts */
        .alert-box {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 18px;
            border-radius: 14px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 25px;
        }

        .alert-box i {
            font-size: 18px;
        }

        .alert-success-soft {
            background: #e8f9ef;
            color: #16834a;
            border: 1px solid #c7eed7;
        }

        .alert-error-soft {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fbcaca;
        }

        .alert-warning-soft {
            background: #ffedd5;
            color: #c2410c;
            border: 1px solid #fcd9b0;
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

        /* Forms */
        .form-label {
            font-size: 12px;
            font-weight: 700;
            color: #3a4763;
            margin-bottom: 7px;
        }

        .form-control,
        .form-select {
            border: 1px solid #e3e7f1;
            border-radius: 10px;
            padding: 11px 13px;
            font-size: 13px;
            color: var(--text);
            background-color: #fbfcff;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-control::placeholder {
            color: #a4aec0;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            background-color: #fff;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
        }

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
        }

        .btn-gradient:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(99, 102, 241, 0.38);
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

        .row-id {
            color: var(--muted);
            font-weight: 700;
        }

        .cell-strong {
            font-weight: 700;
            color: #1f2b46;
        }

        .cell-muted {
            color: var(--muted);
            font-size: 12px;
            max-width: 280px;
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

        .assignee {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .avatar {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #6366f1, #a855f7);
            color: white;
            font-size: 11px;
            font-weight: 800;
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

            <a class="active" href="quality.php">
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
                    <h1 class="page-title">Quality &amp; Defect Management</h1>
                    <p class="page-subtitle">
                        Track software defects and quality testing results.
                    </p>
                </div>

                <div class="status-badge">
                    <span class="status-dot"></span>
                    <?= $openDefects ?> Open Defects
                </div>
            </div>

            <!-- Message -->
            <?php if ($message !== ""): ?>
                <div class="alert-box <?= $messageType === 'error' ? 'alert-error-soft' : 'alert-success-soft' ?>">
                    <i class="bi <?= $messageType === 'error' ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill' ?>"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Quality Summary -->
            <h5 class="section-heading">Quality at a Glance</h5>

            <div class="row g-3 mb-4">

                <div class="col-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-top">
                            <h6>Open Defects</h6>
                            <div class="stat-icon icon-orange">
                                <i class="bi bi-bug"></i>
                            </div>
                        </div>
                        <h3><?= $openDefects ?></h3>
                        <div class="stat-note">Not yet resolved or closed</div>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-top">
                            <h6>Critical Open</h6>
                            <div class="stat-icon icon-red">
                                <i class="bi bi-exclamation-triangle"></i>
                            </div>
                        </div>
                        <h3><?= $criticalOpen ?></h3>
                        <div class="stat-note">Unresolved critical defects</div>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-top">
                            <h6>Checks Passed</h6>
                            <div class="stat-icon icon-green">
                                <i class="bi bi-check2-circle"></i>
                            </div>
                        </div>
                        <h3><?= $passedChecks ?></h3>
                        <div class="stat-note">Quality checks with a pass</div>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-top">
                            <h6>Checks Failed</h6>
                            <div class="stat-icon icon-blue">
                                <i class="bi bi-x-circle"></i>
                            </div>
                        </div>
                        <h3><?= $failedChecks ?></h3>
                        <div class="stat-note">Quality checks with a fail</div>
                    </div>
                </div>

            </div>

            <?php if (count($projects) === 0): ?>

                <div class="alert-box alert-warning-soft">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    Please create a project first.
                </div>

            <?php else: ?>

            <!-- Forms -->
            <div class="row g-3 mb-4">

                <!-- Report a Defect -->
                <div class="col-lg-6">
                    <section class="panel">

                        <h5 class="panel-title">
                            <i class="bi bi-bug text-primary me-2"></i>
                            Report a Defect
                        </h5>

                        <p class="panel-subtitle">
                            Log a defect and assign it to a team member.
                        </p>

                        <form method="POST">
                            <input type="hidden" name="form_type" value="defect">

                            <div class="row g-3">

                                <div class="col-12">
                                    <label class="form-label">Project</label>
                                    <select name="project_id" class="form-select" required>
                                        <?php foreach ($projects as $project): ?>
                                            <option value="<?= (int)$project["id"] ?>">
                                                <?= htmlspecialchars($project["project_name"]) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Defect Title</label>
                                    <input type="text" name="title" class="form-control"
                                           maxlength="200" required
                                           placeholder="e.g. Appointment Scheduling Error">
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control"
                                              rows="3"
                                              placeholder="What happened and how to reproduce it."></textarea>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Severity</label>
                                    <select name="severity" class="form-select" required>
                                        <option value="Low">Low</option>
                                        <option value="Medium" selected>Medium</option>
                                        <option value="High">High</option>
                                        <option value="Critical">Critical</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Assign To</label>
                                    <select name="assigned_to" class="form-select">
                                        <option value="0">Not Assigned</option>
                                        <?php foreach ($members as $member): ?>
                                            <option value="<?= (int)$member["id"] ?>">
                                                <?= htmlspecialchars(
                                                    $member["name"] . " - " . $member["role"]
                                                ) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                            </div>

                            <button type="submit" class="btn-gradient mt-4">
                                <i class="bi bi-check2-circle"></i>
                                Save Defect
                            </button>
                        </form>

                    </section>
                </div>

                <!-- Add Quality Check -->
                <div class="col-lg-6">
                    <section class="panel">

                        <h5 class="panel-title">
                            <i class="bi bi-patch-check text-primary me-2"></i>
                            Add Quality Check
                        </h5>

                        <p class="panel-subtitle">
                            Record the result of a quality test.
                        </p>

                        <form method="POST">
                            <input type="hidden" name="form_type" value="quality">

                            <div class="row g-3">

                                <div class="col-12">
                                    <label class="form-label">Project</label>
                                    <select name="project_id" class="form-select" required>
                                        <?php foreach ($projects as $project): ?>
                                            <option value="<?= (int)$project["id"] ?>">
                                                <?= htmlspecialchars($project["project_name"]) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Quality Check Name</label>
                                    <input type="text" name="check_name" class="form-control"
                                           maxlength="200" required
                                           placeholder="e.g. Patient Record Validation">
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea name="notes" class="form-control" rows="3"
                                              placeholder="Add any findings or remarks."></textarea>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Result</label>
                                    <select name="result" class="form-select" required>
                                        <option value="Pending">Pending</option>
                                        <option value="Pass">Pass</option>
                                        <option value="Fail">Fail</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Check Date</label>
                                    <input type="date" name="checked_at" class="form-control">
                                </div>

                            </div>

                            <button type="submit" class="btn-gradient mt-4">
                                <i class="bi bi-check2-circle"></i>
                                Save Quality Check
                            </button>
                        </form>

                    </section>
                </div>

            </div>

            <?php endif; ?>

            <!-- Defect Register -->
            <section class="panel mb-4">

                <h5 class="panel-title">
                    <i class="bi bi-list-check text-primary me-2"></i>
                    Defect Register
                </h5>

                <p class="panel-subtitle">
                    All reported defects with severity and assignee.
                </p>

                <div class="table-wrap">
                    <table class="pms-table">

                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Project</th>
                                <th>Defect</th>
                                <th>Severity</th>
                                <th>Assigned To</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($defects as $defect): ?>
                                <?php $assignee = $defect["member_name"] ?? ""; ?>
                                <tr>
                                    <td class="row-id">#<?= (int)$defect["id"] ?></td>

                                    <td>
                                        <span class="project-chip">
                                            <?= htmlspecialchars($defect["project_name"]) ?>
                                        </span>
                                    </td>

                                    <td class="cell-strong">
                                        <?= htmlspecialchars($defect["title"]) ?>
                                    </td>

                                    <td><?= severityPill($defect["severity"]) ?></td>

                                    <td>
                                        <?php if ($assignee !== ""): ?>
                                            <span class="assignee">
                                                <span class="avatar">
                                                    <?= htmlspecialchars(strtoupper(mb_substr($assignee, 0, 1))) ?>
                                                </span>
                                                <?= htmlspecialchars($assignee) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="cell-muted">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>

                                    <td><?= defectStatusPill($defect["status"]) ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (count($defects) === 0): ?>
                                <tr>
                                    <td colspan="6" class="empty-state">
                                        <i class="bi bi-inbox"></i>
                                        No defects recorded yet.
                                    </td>
                                </tr>
                            <?php endif; ?>

                        </tbody>

                    </table>
                </div>

            </section>

            <!-- Quality Check Register -->
            <section class="panel">

                <h5 class="panel-title">
                    <i class="bi bi-clipboard-check text-primary me-2"></i>
                    Quality Check Register
                </h5>

                <p class="panel-subtitle">
                    Quality tests with their results and notes.
                </p>

                <div class="table-wrap">
                    <table class="pms-table">

                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Project</th>
                                <th>Check Name</th>
                                <th>Result</th>
                                <th>Date</th>
                                <th>Notes</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($checks as $check): ?>
                                <tr>
                                    <td class="row-id">#<?= (int)$check["id"] ?></td>

                                    <td>
                                        <span class="project-chip">
                                            <?= htmlspecialchars($check["project_name"]) ?>
                                        </span>
                                    </td>

                                    <td class="cell-strong">
                                        <?= htmlspecialchars($check["check_name"]) ?>
                                    </td>

                                    <td><?= resultPill($check["result"]) ?></td>

                                    <td>
                                        <?= htmlspecialchars($check["checked_at"] ?? "") ?>
                                    </td>

                                    <td class="cell-muted">
                                        <?= htmlspecialchars($check["notes"] ?? "") ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (count($checks) === 0): ?>
                                <tr>
                                    <td colspan="6" class="empty-state">
                                        <i class="bi bi-inbox"></i>
                                        No quality checks recorded yet.
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