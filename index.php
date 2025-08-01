<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login-Shikshalaya</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f4f4f4;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
    }

    .login-container {
      background: rgba(255, 255, 255, 0.15);
      /* more transparent */
      border-radius: 16px;
      padding: 30px 25px 25px;
      width: 320px;
      box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
      backdrop-filter: blur(15px);
      -webkit-backdrop-filter: blur(15px);
      border: 1px solid rgba(255, 255, 255, 0.25);
      color: #333;
      box-sizing: border-box;
    }

    .logo-circle {
      width: 120px;
      height: 120px;
      margin: 0 auto 20px;
      border-radius: 50%;
      overflow: hidden;
      border: 3px solid rgba(255, 255, 255, 0.6);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      background: rgba(255, 255, 255, 0.1);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .logo-circle img {
      width: 100%;
      height: 100%;
      border-radius: 50%;
      object-fit: cover;
      /* fills circle without distortion */
      display: block;
      filter: drop-shadow(0 0 4px rgba(0, 0, 0, 0.1));
      transition: filter 0.3s ease;
    }

    .logo-circle img:hover {
      filter: drop-shadow(0 0 8px rgba(0, 0, 0, 0.3));
    }

    h2 {
      text-align: center;
      color: #222;
      margin-bottom: 20px;
      font-weight: 700;
    }

    .form-group {
      margin-bottom: 15px;
    }

    label {
      display: block;
      margin-bottom: 5px;
      color: #444;
      font-weight: 600;
    }

    input[type="text"],
    input[type="password"] {
      width: 100%;
      padding: 10px;
      border-radius: 8px;
      border: 1px solid rgba(255, 255, 255, 0.6);
      background: rgba(255, 255, 255, 0.25);
      color: #222;
      font-size: 14px;
      transition: border-color 0.3s ease, background 0.3s ease;
      box-sizing: border-box;
      backdrop-filter: blur(5px);
    }

    input[type="text"]:focus,
    input[type="password"]:focus {
      border-color: #f59e0b;
      /* amber-500 */
      background: rgba(255, 255, 255, 0.5);
      outline: none;
    }

    button {
      width: 100%;
      padding: 10px;
      background: linear-gradient(90deg, #fbbf24 0%, #f59e0b 100%);
      border: none;
      border-radius: 8px;
      color: #222;
      font-weight: 700;
      cursor: pointer;
      transition: background 0.3s ease, transform 0.2s ease;
      box-shadow: 0 4px 8px rgba(246, 150, 25, 0.6);
    }

    button:hover {
      background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%);
      transform: scale(1.05);
    }

    a {
      display: block;
      text-align: center;
      margin-top: 12px;
      color: #b45309;
      /* amber-700 */
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
      transition: color 0.3s ease;
    }

    a:hover {
      color: #78350f;
      /* amber-900 */
      text-decoration: underline;
    }
  </style>
</head>

<body>

  <?php
  session_start();
  include 'partials/dbconnect.php';
  include 'partials/algorithms.php';

  // Your PHP login logic unchanged
  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];


    $hashed_password = custom_password_hash($password);

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
    $stmt->bind_param("ss", $username, $hashed_password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();

    // Set session values
    $_SESSION['user_id'] = $row['id'];
    $_SESSION['username'] = $row['username'];
    $_SESSION['user_type'] = $row['type'];
    $_SESSION['school_id'] = $row['school_id'];

    // Log the login
    $user_id = $row['id'];
    $school_id = $row['school_id'];
    $conn->query("INSERT INTO login_logs (user_id, school_id) VALUES ($user_id, $school_id)");

    // Redirect based on user type
    switch ($row['type']) {
        case 'superadmin':
            header("Location: superadmin/superadmin_dashboard.php");
            break;
        case 'admin':
            header("Location: admin/admin_dashboard.php");
            break;
        case 'teacher':
            header("Location: teacher/teacher_dashboard.php");
            break;
        case 'parent':
            header("Location: parents/parents_dashboard.php");
            break;
        case 'accountant':
            header("Location: accountant/accountant_dashboard.php");
            break;
        default:
            echo "Invalid user type.";
    }
    exit();
} else {
    echo "<script>alert('Invalid username or password');</script>";
}


    $stmt->close();
  }
  ?>

  <div class="login-container">
    <h2>Login to Shikshalaya</h2>
    <div class="logo-circle">
      <img src="images/logo.png" alt="Shikshalaya Logo" />
    </div>
    <form action="" method="post">
      <div class="form-group">
        <label for="username">Username:</label>
        <input type="text" id="username" name="username" required />
      </div>
      <div class="form-group">
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required />
      </div>
      <button type="submit">Login</button>
      <div class="form-group">
        <a href="forgot_password.php">Forgot Password?</a>
      </div>
    </form>
  </div>
</body>

</html>