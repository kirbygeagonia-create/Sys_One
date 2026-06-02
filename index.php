<?php require_once __DIR__ . '/includes/header.php'; ?>

<section class="hero">
    <div class="hero-content">
        <h1>Trade Skills, Not Money.</h1>
        <p class="hero-subtitle">
            Teach what you know. Learn what you don't. Earn credits and collect verified skill badges along the way.
        </p>
        <?php if (!$currentUser): ?>
            <div class="hero-actions">
                <a href="#" class="btn btn-primary btn-lg" onclick="openModal('registerModal'); return false;">Get Started — It's Free</a>
                <a href="/pages/browse.php" class="btn btn-outline btn-lg">Browse Skills</a>
            </div>
            <p class="mt-16 hero-muted-text">
                Already have an account? <a href="#" onclick="openModal('loginModal'); return false;" class="hero-link">Log in</a>
            </p>
        <?php else: ?>
            <div class="hero-actions">
                <a href="/pages/dashboard.php" class="btn btn-primary btn-lg">Go to Dashboard</a>
                <a href="/pages/browse.php" class="btn btn-outline btn-lg">Find Teachers</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="container">
        <h2 class="section-title">How It Works</h2>
        <div class="steps-grid">
            <div class="step-card">
                <div class="step-number">1</div>
                <h3>List Your Skills</h3>
                <p>Tell the community what you can teach and what you want to learn.</p>
            </div>
            <div class="step-card">
                <div class="step-number">2</div>
                <h3>Connect & Trade</h3>
                <p>Find matches, request sessions, and swap knowledge. Teaching earns you credits.</p>
            </div>
            <div class="step-card">
                <div class="step-number">3</div>
                <h3>Earn Badges</h3>
                <p>Get endorsed by your students. Build a verified profile of skills you've mastered.</p>
            </div>
        </div>
    </div>
</section>

<section class="section section-alt">
    <div class="container">
        <h2 class="section-title">Why SkillLoop is Different</h2>
        <div class="features-grid">
            <div class="feature-card">
                <span class="feature-icon"><i class="fas fa-coins"></i></span>
                <h3>Credit Economy</h3>
                <p>No need for direct swaps. Teach anyone, earn credits, learn from anyone else. The system finds the balance.</p>
            </div>
            <div class="feature-card">
                <span class="feature-icon"><i class="fas fa-medal"></i></span>
                <h3>Skill Badges</h3>
                <p>Every completed session earns you a verifiable badge endorsed by your teacher. Build your credential portfolio.</p>
            </div>
            <div class="feature-card">
                <span class="feature-icon"><i class="fas fa-exchange-alt"></i></span>
                <h3>No Money Involved</h3>
                <p>Pure skill barter. No payments, no subscriptions. Your knowledge is the currency.</p>
            </div>
            <div class="feature-card">
                <span class="feature-icon"><i class="fas fa-star"></i></span>
                <h3>Any Skill</h3>
                <p>From guitar to Python, baking to photography. If you can teach it, it belongs here.</p>
            </div>
        </div>
    </div>
</section>

<!-- Login Modal -->
<div class="modal-overlay" id="loginModal">
    <div class="modal">
        <h2>Login</h2>
        <p class="text-muted mb-16">Welcome back! Sign in to continue.</p>
        <form method="POST" action="/auth/login.php">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="login_email">Email</label>
                <input type="email" id="login_email" name="email" placeholder="you@example.com" required>
            </div>
            <div class="form-group">
                <label for="login_password">Password</label>
                <input type="password" id="login_password" name="password" placeholder="Your password" required>
            </div>
            <div class="text-right mb-12">
                <a href="/auth/forgot_password.php" class="text-sm">Forgot password?</a>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>
        <p class="auth-footer-text">
            Don't have an account? <a href="#" onclick="closeModal('loginModal'); openModal('registerModal'); return false;">Sign up</a>
        </p>
        <div class="text-center mt-12">
            <button class="btn btn-outline btn-sm" onclick="closeModal('loginModal')">Cancel</button>
        </div>
    </div>
</div>

<!-- Register Modal -->
<div class="modal-overlay" id="registerModal">
    <div class="modal">
        <h2>Join SkillLoop</h2>
        <p class="text-muted mb-16">Trade skills. Earn badges. Learn anything.</p>
        <form method="POST" action="/auth/register.php">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="reg_name">Full Name</label>
                <input type="text" id="reg_name" name="name" placeholder="e.g. Juan Dela Cruz" required>
            </div>
            <div class="form-group">
                <label for="reg_email">Email</label>
                <input type="email" id="reg_email" name="email" placeholder="you@example.com" required>
            </div>
            <div class="form-group">
                <label for="reg_password">Password</label>
                <input type="password" id="reg_password" name="password" placeholder="At least 8 characters" oninput="checkPasswordStrength(this.value, 'regPasswordStrength')" required>
                <div class="password-strength" id="regPasswordStrength"></div>
                <p class="form-hint">Must be 8+ characters with 1 uppercase letter and 1 number.</p>
            </div>
            <div class="form-group">
                <label for="reg_confirm">Confirm Password</label>
                <input type="password" id="reg_confirm" name="confirm_password" placeholder="Repeat your password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Create Account</button>
        </form>
        <p class="auth-footer-text">
            Already have an account? <a href="#" onclick="closeModal('registerModal'); openModal('loginModal'); return false;">Log in</a>
        </p>
        <div class="text-center mt-12">
            <button class="btn btn-outline btn-sm" onclick="closeModal('registerModal')">Cancel</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>