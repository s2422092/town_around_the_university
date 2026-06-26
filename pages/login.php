<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// データベース接続情報
$host = 'localhost';
$user = 'データベース名';
$password = 'データベースパスワード';
$dbname = 'データベース名';

// PostgreSQLへの接続
$dbconn = pg_connect("host=$host user=$user password=$password dbname=$dbname");
if (!$dbconn) {
    die('データベースに接続できません: ' . pg_last_error());
}

$message = ''; // メッセージ表示用変数

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $uname = trim($_POST['username']); // HTMLフォームのname属性に合わせて変更
    $upass = trim($_POST['password']); // HTMLフォームのname属性に合わせて変更

    if (empty($uname) || empty($upass)) {
        $message = 'ユーザー名またはパスワードが空です。';
    } else {
        $sql = "SELECT user_id, upass FROM users WHERE uname = $1"; // 'users' テーブルを使用
        $result = pg_query_params($dbconn, $sql, array($uname));

        if (!$result) {
            $message = 'データベースエラーが発生しました。';
        } elseif (pg_num_rows($result) === 1) {
            $row = pg_fetch_assoc($result);
            if (password_verify($upass, $row['upass'])) {
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['username'] = $uname;
                $message = 'ログイン成功！';
                // ログイン成功後のリダイレクト先を index.php に設定
                header('Location: index.php');
                exit;
            } else {
                $message = 'パスワードが間違っています。';
            }
        } else {
            $message = 'ユーザー名が見つかりません。';
        }
    }
    $_SESSION['message'] = $message; // メッセージをセッションに保存
}

pg_close($dbconn);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> ログイン</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Hachi+Maru+Pop&display=swap" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <h2> ログイン</h2>
        <?php
        if (isset($_SESSION['message'])) {
            echo '<p class="message">' . $_SESSION['message'] . '</p>';
            unset($_SESSION['message']);
        }
        ?>
        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="username">ユーザー名:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">パスワード:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">ログイン</button>
        </form>
        <p class="link-text">アカウントをお持ちでないですか？ <a href="register.php">新規登録はこちら</a></p>
    </div>
</body>
</html>
