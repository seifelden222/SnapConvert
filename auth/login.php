<?php
session_start();

include __DIR__ . '/../include/DB/db.php';
include __DIR__ . '/../include/temb/header.php';
$email = "";
$err = [];
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];
    $pass = $_POST["pass"];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err['email'] = "Invalid Email";
    }
    if (empty($pass)) {
        $err['pass'] = "Enter Password";
    }
    if (empty($err)) {

        // Fetch user by email only, then verify hashed password
        $statment = $connect->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $statment->execute(array($email));
        $result = $statment->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            // verify password hash
            $verified = false;
            if (isset($result['password']) && password_verify($pass, $result['password'])) {
                $verified = true;
            } else {
                // Fallback for existing plaintext-stored passwords: if stored value equals submitted password,
                // accept and upgrade to a hashed value.
                if (isset($result['password']) && $pass === $result['password']) {
                    // Re-hash and update DB
                    $newHash = password_hash($pass, PASSWORD_DEFAULT);
                    try {
                        $up = $connect->prepare("UPDATE users SET `password` = ? WHERE user_id = ?");
                        $up->execute(array($newHash, $result['user_id']));
                        $verified = true;
                    } catch (PDOException $e) {
                        // ignore update failure, proceed only if password matches
                        $verified = true;
                    }
                }
            }

            if ($verified) {
                if ($result['status'] == true) {
                    if ($result['role'] == 'admin') {
                        $_SESSION['login_admin'] = $email;
                        header('Location: ../admin/dashboard.php');
                        exit;
                    } else if ($result['role'] == 'user') {
                        $_SESSION['login_user'] = $email;
                        header('Location: ../index.php');
                        exit;
                    }
                } else {
                    $_SESSION['massege'] = "Your Account Is Blocked";
                }
            } else {
                $_SESSION['massege'] = "Your Email or Password Not Correct";
            }
        } else {
            $_SESSION['massege'] = "Your Email or Password Not Correct";
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
                    <label class="text-center ">Email address</label>
                    <input type="email" class="form-control" name="email" aria-describedby="emailHelp" placeholder="Enter email" value="<?= $email ?>">
                    <?php
                    if (!empty($err['email'])) {
                        echo '<div class="alert alert-danger">' . $err['email'] . '</div>';
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
                <button type="submit" class="btn btn-primary btn-block mt-2">Login</button>
            </form>

        </div>
    </div>
</div>
<?php
include __DIR__ . '/../include/temb/footer.php';
?>