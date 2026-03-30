<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login & Signup | Wafiq's Blog</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&display=swap"
      rel="stylesheet"
    />
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
      integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
      crossorigin="anonymous"
    />
    <link rel="stylesheet" href="css/styles.css" />
  </head>
  <body>
    <div class="space-stars" aria-hidden="true"></div>

    <nav class="navbar navbar-expand-lg border-bottom sticky-top cosmic-nav">
      <div class="container">
        <a class="navbar-brand fw-bold" href="index.html">Wafiq's Postulations</a>
        <a class="btn btn-outline-info rounded-pill px-3 ms-auto" href="index.html">Back Home</a>
      </div>
    </nav>

    <main class="container py-4 position-relative">
      <div class="auth-shell">
        <section class="auth-card p-3 p-md-4">
          <header class="mb-3">
            <h1 class="h3 mb-1">Account Access</h1>
            <p class="feed-subtitle mb-0">Sign up to interact, or log in to continue.</p>
          </header>

          <ul class="nav nav-pills gap-2 mb-3" id="auth-tabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="btn btn-info rounded-pill px-3 fw-semibold active" id="login-tab" type="button">
                Login
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="btn btn-outline-info rounded-pill px-3 fw-semibold" id="signup-tab" type="button">
                Signup
              </button>
            </li>
          </ul>

          <p id="auth-status" class="compose-status mb-3" aria-live="polite"></p>

          <form id="login-form" class="auth-form">
            <div class="mb-3">
              <label class="compose-label" for="login-username">Username</label>
              <input class="compose-input" id="login-username" name="username" autocomplete="username" required />
            </div>
            <div class="mb-3">
              <label class="compose-label" for="login-password">Password</label>
              <input
                class="compose-input"
                id="login-password"
                name="password"
                type="password"
                autocomplete="current-password"
                required
              />
            </div>
            <button class="btn btn-info rounded-pill px-4 fw-semibold" type="submit">Login</button>
          </form>

          <form id="signup-form" class="auth-form d-none">
            <div class="mb-3">
              <label class="compose-label" for="signup-username">Username</label>
              <input class="compose-input" id="signup-username" name="username" minlength="3" maxlength="50" required />
            </div>
            <div class="mb-3">
              <label class="compose-label" for="signup-email">Email (optional)</label>
              <input class="compose-input" id="signup-email" name="email" type="email" autocomplete="email" />
            </div>
            <div class="mb-3">
              <label class="compose-label" for="signup-password">Password</label>
              <input
                class="compose-input"
                id="signup-password"
                name="password"
                type="password"
                minlength="6"
                autocomplete="new-password"
                required
              />
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="signup-show-email" name="show_email" />
              <label class="form-check-label" for="signup-show-email">Show email on my profile</label>
            </div>
            <button class="btn btn-info rounded-pill px-4 fw-semibold" type="submit">Create Account</button>
          </form>
        </section>
      </div>
    </main>

    <script src="js/auth.js"></script>
  </body>
</html>
