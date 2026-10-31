<?php
require_once "config/database.php";

$message = "";
$messageType = "success";

// Get all projects for the dropdown
$projectStmt = $pdo->query(
    "SELECT id, project_name FROM projects ORDER BY id DESC"
);
$projectList = $projectStmt->fetchAll(PDO::FETCH_ASSOC);

// Save a team member
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $projectId = (int)($_POST["project_id"] ?? 0);
    $name = trim($_POST["name"] ?? "");
    $role = trim($_POST["role"] ?? "");
    $email = trim($_POST["email"] ?? "");

    $validRoles = [
        "Project Manager",
        "Backend Developer",
        "Frontend Developer",
        "Database Developer",
        "QA Engineer"
    ];

    $validProject = false;

    foreach ($projectList as $project) {
        if ((int)$project["id"] === $projectId) {
            $validProject = true;
            break;
        }
    }

    if (
        !$validProject ||
        $name === "" ||
        !in_array($role, $validRoles, true) ||
        ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL))
    ) {
        $message = "Please enter valid team member details.";
        $messageType = "error";
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO team_members
            (project_id, name, role, email)
            VALUES (?, ?, ?, ?)"
        );

        $stmt->execute([
            $projectId,
            $name,
            $role,
            $email !== "" ? $email : null
        ]);

        $message = "Team member added successfully!";
    }
}

// Get all saved team members
$stmt = $pdo->query(
    "SELECT team_members.*, projects.project_name
     FROM team_members
     INNER JOIN projects
     ON team_members.project_id = projects.id
     ORDER BY team_members.id DESC"
);

$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Display summary and helpers (UI only)
$totalMembers = count($members);
$developerCount = 0;
$qaCount = 0;
$managerCount = 0;

foreach ($members as $m) {
    if (in_array($m["role"], ["Backend Developer", "Frontend Developer", "Database Developer"], true)) {
        $developerCount++;
    } elseif ($m["role"] === "QA Engineer") {
        $qaCount++;
    } elseif ($m["role"] === "Project Manager") {
        $managerCount++;
    }
}

function rolePill($role)
{
    $map = [
        "Project Manager"    => "pill-purple",
        "Backend Developer"  => "pill-blue",
        "Frontend Developer" => "pill-green",
        "Database Developer" => "pill-orange",
        "QA Engineer"        => "pill-red",
    ];

    $class = $map[$role] ?? "pill-muted";

    return '<span class="pill ' . $class . '">'
        . htmlspecialchars((string)$role) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Team & Resources | Smart Healthcare PMS</title>

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

        .member {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #6366f1, #a855f7);
            color: white;
            font-size: 13px;
            font-weight: 800;
            flex-shrink: 0;
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

        .pill-purple {
            background: #f3e8ff;
            color: #7e22ce;
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

            <a class="active" href="team.php">
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
                    <h1 class="page-title">Team Management</h1>
                    <p class="page-subtitle">
                        Add and manage project team members.
                    </p>
                </div>

                <div class="status-badge">
                    <span class="status-dot"></span>
                    <?= $totalMembers ?> Team Members
                </div>
            </div>

            <!-- Message -->
            <?php if ($message !== ""): ?>
                <div class="alert-box <?= $messageType === 'error' ? 'alert-error-soft' : 'alert-success-soft' ?>">
                    <i class="bi <?= $messageType === 'error' ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill' ?>"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Team summary -->
            <h5 class="section-heading">Team at a Glance</h5>

            <div class="row g-3 mb-4">

                <div class="col-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-top">
                            <h6>Total Members</h6>
                            <div class="stat-icon icon-blue">
                                <i class="bi bi-people"></i>
                            </div>
                        </div>
                        <h3><?= $totalMembers ?></h3>
                        <div class="stat-note">Across all projects</div>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-top">
                            <h6>Project Managers</h6>
                            <div class="stat-icon icon-orange">
                                <i class="bi bi-person-badge"></i>
                            </div>
                        </div>
                        <h3><?= $managerCount ?></h3>
                        <div class="stat-note">Leading project delivery</div>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-top">
                            <h6>Developers</h6>
                            <div class="stat-icon icon-green">
                                <i class="bi bi-code-slash"></i>
                            </div>
                        </div>
                        <h3><?= $developerCount ?></h3>
                        <div class="stat-note">Backend, frontend and database</div>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-top">
                            <h6>QA Engineers</h6>
                            <div class="stat-icon icon-red">
                                <i class="bi bi-bug"></i>
                            </div>
                        </div>
                        <h3><?= $qaCount ?></h3>
                        <div class="stat-note">Testing and quality checks</div>
                    </div>
                </div>

            </div>

            <?php if (count($projectList) === 0): ?>

                <div class="alert-box alert-warning-soft">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    Please create a project before adding team members.
                </div>

            <?php else: ?>

            <!-- Add Team Member -->
            <section class="panel mb-4">

                <h5 class="panel-title">
                    <i class="bi bi-person-plus text-primary me-2"></i>
                    Add Team Member
                </h5>

                <p class="panel-subtitle">
                    Assign a new member to a project with their role.
                </p>

                <form method="POST">

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label">Select Project</label>
                            <select name="project_id" class="form-select" required>
                                <?php foreach ($projectList as $project): ?>
                                    <option value="<?= (int)$project["id"] ?>">
                                        <?= htmlspecialchars($project["project_name"]) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control"
                                   maxlength="100" required
                                   placeholder="e.g. Ayesha Khan">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="">Select Role</option>
                                <option value="Project Manager">Project Manager</option>
                                <option value="Backend Developer">Backend Developer</option>
                                <option value="Frontend Developer">Frontend Developer</option>
                                <option value="Database Developer">Database Developer</option>
                                <option value="QA Engineer">QA Engineer</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email (Optional)</label>
                            <input type="email" name="email" class="form-control"
                                   maxlength="150"
                                   placeholder="name@example.com">
                        </div>

                    </div>

                    <button type="submit" class="btn-gradient mt-4">
                        <i class="bi bi-check2-circle"></i>
                        Add Team Member
                    </button>

                </form>

            </section>

            <?php endif; ?>

            <!-- Team Members List -->
            <section class="panel">

                <h5 class="panel-title">
                    <i class="bi bi-people text-primary me-2"></i>
                    Team Members List
                </h5>

                <p class="panel-subtitle">
                    Everyone assigned to your projects.
                </p>

                <div class="table-wrap">
                    <table class="pms-table">

                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Email</th>
                                <th>Project</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($members as $member): ?>
                                <tr>
                                    <td class="row-id">#<?= (int)$member["id"] ?></td>

                                    <td>
                                        <span class="member">
                                            <span class="avatar">
                                                <?= htmlspecialchars(strtoupper(mb_substr($member["name"], 0, 1))) ?>
                                            </span>
                                            <span class="cell-strong">
                                                <?= htmlspecialchars($member["name"]) ?>
                                            </span>
                                        </span>
                                    </td>

                                    <td><?= rolePill($member["role"]) ?></td>

                                    <td class="cell-muted">
                                        <?= htmlspecialchars($member["email"] ?? "") ?>
                                    </td>

                                    <td>
                                        <span class="project-chip">
                                            <?= htmlspecialchars($member["project_name"]) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (count($members) === 0): ?>
                                <tr>
                                    <td colspan="5" class="empty-state">
                                        <i class="bi bi-inbox"></i>
                                        No team members added yet.
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