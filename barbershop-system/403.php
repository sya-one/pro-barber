<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Forbidden | The Professional Barbershop</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💈</text></svg>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #000;
            color: #fff;
            font-family: 'Inter', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow: hidden;
        }
        .error-container {
            text-align: center;
            z-index: 2;
        }
        .error-code {
            font-size: 8rem;
            font-weight: 700;
            color: #0FA958;
            text-shadow: 0 0 20px rgba(15,169,88,0.5);
            line-height: 1;
        }
        .message {
            font-size: 1.5rem;
            margin: 1rem 0;
            color: rgba(255,255,255,0.9);
        }
        .sub-message {
            color: rgba(255,255,255,0.6);
            margin-bottom: 2rem;
        }
        .btn-home {
            background: #0FA958;
            border: none;
            padding: 0.75rem 2rem;
            color: #fff;
            font-weight: 600;
            border-radius: 5px;
            transition: 0.3s;
        }
        .btn-home:hover {
            background: #0d9147;
            color: #fff;
        }
        .floating-icon {
            position: absolute;
            font-size: 3rem;
            opacity: 0.1;
            animation: float 8s infinite;
        }
        @keyframes float {
            0% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(10deg); }
            100% { transform: translateY(0px) rotate(0deg); }
        }
    </style>
</head>
<body>
    <!-- Background floating scissors -->
    <i class="fas fa-cut floating-icon" style="top:10%; left:10%;"></i>
    <i class="fas fa-cut floating-icon" style="top:20%; right:15%; animation-delay: 2s;"></i>
    <i class="fas fa-cut floating-icon" style="bottom:15%; left:20%; animation-delay: 4s;"></i>
    <i class="fas fa-cut floating-icon" style="bottom:10%; right:10%; animation-delay: 6s;"></i>

    <div class="error-container">
        <div class="error-code">403</div>
        <div class="message">This area is <strong style="color:#0FA958;">VIP only</strong> – and you’re not on the list!</div>
        <div class="sub-message">
            Even the sharpest clippers can’t cut through this door.<br>
            If you think this is a mistake, talk to the boss – or just back away slowly.
        </div>
        <a href="index.php" class="btn-home"><i class="fas fa-home me-2"></i>Back to Safety</a>
        <br><br>
        <small style="color: rgba(255,255,255,0.5);">Not the boss? Try logging in first.</small>
    </div>

    <!-- Subtle barber pole SVG background -->
    <div style="position:fixed; bottom:0; right:0; opacity:0.05; pointer-events:none;">
        <i class="fas fa-cut" style="font-size:20rem;"></i>
    </div>
</body>
</html>