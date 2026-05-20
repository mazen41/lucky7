<?php
// 1. Start the session
// This is necessary to access and manipulate the session data.
session_start();

// 2. Unset all session variables
// This clears all data stored in the $_SESSION superglobal array.
$_SESSION = [];

// 3. Destroy the session cookie
// This is a robust way to ensure the session cookie is removed from the user's browser.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Destroy the session on the server
// This permanently removes the session data from the server.
session_destroy();

// 5. Output HTML with JavaScript to clear browser storage and redirect
// The PHP part is done. Now, we send instructions to the user's browser.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Logging Out...</title>
    <script>
        // Use an immediately invoked function to avoid polluting the global scope.
        (function() {
            // Clear all items from localStorage.
            localStorage.clear();

            // Clear all items from sessionStorage.
            sessionStorage.clear();
            
            // Redirect the user to the main index page.
            // Change '/' to '/login.php' or your specific landing page if needed.
            window.location.href = '/'; 
        })();
    </script>
</head>
<body>
    <p>Logging you out... If you are not redirected automatically, <a href="/">click here</a>.</p>
</body>
</html>