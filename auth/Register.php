<?php
session_start();

include __DIR__ . '/../include/DB/db.php';
include __DIR__ . '/../include/temb/header.php';

$email = "";
$username = "";
$phone = "";
$err = [];

if ($_SERVER["REQUEST_METHOD"] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $pass = $_POST['pass'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err['email'] = "Invalid Email";
    }

    if (empty($username) || strlen($username) < 3) {
        $err['username'] = "Username must be at least 3 characters";
    }

    if (empty($phone)) {
        $err['phone'] = "Phone number is required";
    }

    if (empty($pass) || strlen($pass) < 4) {
        $err['pass'] = "Password must be at least 4 characters";
    }

    if (empty($err)) {
        // Check if email already exists
    $check = $connect->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->execute(array($email));
        if ($check->rowCount() > 0) {
            $_SESSION['massege'] = "Email is already registered";
            header('Location: Register.php');
            exit;
        }

        // Hash the password before storing
        $hash = password_hash($pass, PASSWORD_DEFAULT);

        // Insert user with necessary NOT NULL columns (created_at set to NOW())
        $insert = $connect->prepare("INSERT INTO users (username, email, phone, `password`, created_at, role, status) VALUES (?, ?, ?, ?, NOW(), 'user', 1)");
        try {
            $insert->execute(array($username, $email, $phone, $hash));
            $_SESSION['massege'] = "Registration successful. Please login.";
            header('Location:login.php');
            exit;
        } catch (PDOException $e) {
            // Log error in real app. Show generic message here.
            $_SESSION['massege'] = "Registration failed. Try again later.";
            header('Location: Register.php');
            exit;
        }
    }
}

?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <h2 class="text-center my-4">Welcome to the Home Page</h2>
            
            <?php
            if(isset($_SESSION["massege"])){
                echo "<div class='alert alert-danger'>".$_SESSION["massege"]."</div>";
                unset($_SESSION["massege"]);
            }
            ?>
            <form action="" method="post" enctype="multipart/form-data">

                <div class="form-group my-4">
                    <label class="text-center">Username</label>
                    <input type="text" class="form-control" name="username" placeholder="Enter username" value="<?= htmlspecialchars($username) ?>">
                    <?php
                    if (!empty($err['username'])) {
                        echo '<div class="alert alert-danger">' . $err['username'] . '</div>';
                    }
                    ?>
                </div>

                <div class="form-group my-4">
                    <label class="text-center ">Email address</label>
                    <input type="email" class="form-control" name="email" aria-describedby="emailHelp" placeholder="Enter email" value="<?= htmlspecialchars($email) ?>">
                    <?php
                    if (!empty($err['email'])) {
                        echo '<div class="alert alert-danger">' . $err['email'] . '</div>';
                    }
                    ?>
                </div>

                <div class="form-group my-4">
                    <label class="text-center">Phone Number</label>
                    <input type="tel" class="form-control" name="phone" placeholder="Enter phone number" value="<?= htmlspecialchars($phone) ?>">
                    <?php
                    if (!empty($err['phone'])) {
                        echo '<div class="alert alert-danger">' . $err['phone'] . '</div>';
                    }
                    ?>
                </div>

                <div class="form-group">
                    <label class="text-center">Password</label>
                    <input type="password" class="form-control" name="pass" placeholder="Password">
                    <?php
                    if (!empty($err['pass'])) {
                        echo '<div class="alert alert-danger">' . $err['pass'] . '</div>';
                    }
                    ?>
                </div>
                <button type="submit" class="btn btn-primary btn-block mt-2">Register</button>
            </form>

        </div>
    </div>
</div>
<?php
include __DIR__ . '/../include/temb/footer.php';
?>