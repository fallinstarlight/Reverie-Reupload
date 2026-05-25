<!DOCTYPE html>
<html lang="en">

<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de inventarios Reverie">
    <title>Reverie: Log in</title>
</head>

<body style="background-color: #fff1f5">
    <h1 class="titulos">Bienvenido a Spiral, panadería y repostería, favor de iniciar sesión </h1>
    <div class="container box">
        <div class="container">
            <img src="assets/photos/logo.png" alt="logo de la página" style="max-width: 100%;">
        </div>
        <form style="padding-bottom: 3%" class="login-form" id='loginform'>
            <div class="mb-3" style="padding-top: 3%;">
                <label for="username" class="labels">Usuario</label>
                <input type="text" class="form-control textboxes rounded-pill" id="username" placeholder="Ingrese su usuario">
            </div>
            <div class="mb-3" style="padding-top: 3%; padding-bottom: 3%;">
                <label for="password" class="labels">Contraseña</label>
                <input type="password" class="form-control textboxes rounded-pill" id="password" placeholder="Ingrese su contraseña">
            </div>
                <button type="submit" id="s_btn" class="btn btn-primary w-100 botones labels">Iniciar Sesión</button>
        </form>
    </div>
    <footer style="padding-top: 10%; color:#ffffff">
        <div class="container"
            style="max-width: 100%; height: auto; min-height: 200px; background: linear-gradient(135deg, #e556a5 10%, #ff0d86 100%);">
            <br> Powered by Reverie<br>
            Copyright 2026<br>
            Luna Hidalgo Francisco Emmanuel<br>
            Arrieta Prado Isaaías<br>
            Tolentino Segovia Luis Fernando<br>
        </div>
    </footer>
<script src="js/login.js"></script>
</body>
</html>