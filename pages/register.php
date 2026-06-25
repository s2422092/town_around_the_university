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
    $uname = trim($_POST['uname']);
    $upass = trim($_POST['upass']);

    if (empty($uname) || empty($upass)) {
        $message = 'ユーザー名またはパスワードが空です。';
    } else {
        // ユーザー名の存在チェック
        $check_sql = "SELECT 1 FROM users WHERE uname = $1"; // 'users' テーブルを使用
        $check_result = pg_query_params($dbconn, $check_sql, array($uname));

        if (!$check_result) {
            $message = 'データベースエラーが発生しました。';
        } elseif (pg_num_rows($check_result) > 0) {
            $message = 'ユーザー名が既に存在します。別のユーザー名をお試しください。';
        } else {
            // パスワードのハッシュ化
            $hashed_pass = password_hash($upass, PASSWORD_DEFAULT);

            // ユーザー登録
            $insert_sql = "INSERT INTO users (uname, upass) VALUES ($1, $2)"; // 'users' テーブルを使用
            $result = pg_query_params($dbconn, $insert_sql, array($uname, $hashed_pass));

            if ($result) {
                $message = 'ユーザーが登録されました！ログインしてください。';
                header('Location: login.php'); // 登録成功後、ログインページへリダイレクト
                exit;
            } else {
                $message = 'ユーザー登録に失敗しました。';
            }
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
    <title> 新規登録</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Hachi+Maru+Pop&display=swap" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <h2> 新規登録</h2>
        <?php
        if (isset($_SESSION['message'])) {
            echo '<p class="message">' . $_SESSION['message'] . '</p>';
            unset($_SESSION['message']);
        }
        ?>
        <form action="register.php" method="POST">
            <div class="form-group">
                <label for="uname">ユーザー名:</label>
                <input type="text" id="uname" name="uname" required>
            </div>
            <div class="form-group">
                <label for="upass">パスワード:</label>
                <input type="password" id="upass" name="upass" required>
            </div>
            <button type="submit">登録</button>
        </form>
        <p class="link-text">アカウントをお持ちですか？ <a href="login.php">ログインはこちら</a></p>
    </div>
</body>
</html>