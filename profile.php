<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

$user_id = $_SESSION['user_id'];
$sql = "SELECT name, email, created_at FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
  <title>Profile - FinWise</title>
  <link href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css" rel="stylesheet">
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f5f7fa;
      padding: 2rem;
    }
    .profile-card {
      background: #fff;
      padding: 2rem;
      border-radius: 12px;
      box-shadow: 0 4px 16px rgba(0,0,0,0.1);
      max-width: 500px;
      margin: auto;
      text-align: center;
    }
    .profile-card img {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      margin-bottom: 1rem;
    }
    .profile-card h2 {
      color: #2c3e50;
      margin-bottom: 0.5rem;
    }
    .profile-card p {
      color: #666;
      margin: 0.2rem 0;
    }
    .profile-card .btn {
      margin-top: 1rem;
      padding: 0.6rem 1.2rem;
      background: #27ae60;
      color: #fff;
      border: none;
      border-radius: 6px;
      cursor: pointer;
    }
    .profile-card .btn:hover {
      background: #219150;
    }
  </style>
</head>
<body>

<div class="profile-card">
  <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=2c3e50&color=fff" alt="Avatar" />
  <h2><?php echo htmlspecialchars($user['name']); ?></h2>
  <p><i class="ri-mail-line"></i> <?php echo htmlspecialchars($user['email']); ?></p>
  <p><i class="ri-calendar-line"></i> Joined: <?php echo date("d M Y", strtotime($user['created_at'])); ?></p>
  <button class="btn" onclick="location.href='edit_profile.php'"><i class="ri-edit-line"></i> Edit Profile</button>
</div>

</body>
</html>
