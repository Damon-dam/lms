<?php
session_start();
include("db.php");

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$results = [];
$total = 0;

$search = "";

// Secure search using prepared statements
if (isset($_GET['query']) && !empty(trim($_GET['query']))) {
    $search = trim($_GET['query']);

    $sql = "SELECT title, author, category FROM books 
            WHERE title LIKE ? 
            OR author LIKE ? 
            OR category LIKE ?";

    $stmt = mysqli_prepare($conn, $sql);

    $param = "%" . $search . "%";
    mysqli_stmt_bind_param($stmt, "sss", $param, $param, $param);

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $results[] = $row;
    }

    $total = count($results);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Search Books</title>

    <style>
        body {
            font-family: "Segoe UI", sans-serif;
            background: linear-gradient(to right, #eef2f3, #dfe9f3);
            margin: 0;
        }

        .container {
            max-width: 1000px;
            margin: 50px auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        h2 {
            margin-bottom: 10px;
            color: #1d2671;
        }

        .subtitle {
            margin-bottom: 20px;
            color: #666;
        }

        form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        input {
            flex: 1;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 10px;
            outline: none;
        }

        input:focus {
            border-color: #1d2671;
        }

        button {
            padding: 12px 20px;
            background: #1d2671;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: 0.3s;
        }

        button:hover {
            background: #c33764;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            overflow: hidden;
            border-radius: 10px;
        }

        th {
            background: #1d2671;
            color: white;
            padding: 14px;
            text-align: left;
        }

        td {
            padding: 14px;
            border-bottom: 1px solid #eee;
        }

        tr:hover {
            background: #f5f7ff;
        }

        .back {
            display: inline-block;
            margin-bottom: 15px;
            color: #1d2671;
            text-decoration: none;
            font-weight: bold;
        }

        .no-result {
            text-align: center;
            padding: 20px;
            color: #888;
        }

        .count {
            margin-bottom: 15px;
            font-weight: bold;
            color: #333;
        }
    </style>
</head>

<body>

<div class="container">

    <a class="back" href="user_dashboard.php">← Back to Dashboard</a>

    <h2>🔍 Search Books</h2>
    <div class="subtitle">Find books by title, author, or category</div>

    <form method="GET">
        <input type="text" name="query" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search books..." required>
        <button type="submit">Search</button>
    </form>

    <?php if (isset($_GET['query'])): ?>
        <div class="count">Results found: <?php echo $total; ?></div>
    <?php endif; ?>

    <table>
        <tr>
            <th>Title</th>
            <th>Author</th>
            <th>Category</th>
        </tr>

        <?php if ($total > 0): ?>
            <?php foreach ($results as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                    <td><?php echo htmlspecialchars($row['author']); ?></td>
                    <td><?php echo htmlspecialchars($row['category']); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php elseif (isset($_GET['query'])): ?>
            <tr>
                <td colspan="3" class="no-result">No books found 😢</td>
            </tr>
        <?php endif; ?>

    </table>

</div>

</body>
</html>