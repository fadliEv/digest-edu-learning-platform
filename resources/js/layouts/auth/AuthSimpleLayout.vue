<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="auth-light-root">
        <!-- Background Light Glows -->
        <div class="auth-light-bg">
            <div class="glow-blob blob-top-right"></div>
            <div class="glow-blob blob-bottom-left"></div>
        </div>

        <!-- Navbar Header -->
        <header class="auth-header">
            <Link :href="home()" class="auth-logo-link">
                <div class="logo-circle-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z" fill="currentColor" opacity="0.3"/>
                        <path d="M8 6.5C8 5.12 9.12 4 10.5 4S13 5.12 13 6.5 11.88 9 10.5 9 8 7.88 8 6.5zM15.5 4C14.12 4 13 5.12 13 6.5S14.12 9 15.5 9 18 7.88 18 6.5 16.88 4 15.5 4zM12 10c-3.31 0-6 2.69-6 6h2c0-2.21 1.79-4 4-4s4 1.79 4 4h2c0-3.31-2.69-6-6-6z" fill="currentColor"/>
                    </svg>
                </div>
                <span class="logo-text">Digest<span class="logo-text-accent">Edu</span></span>
            </Link>
        </header>

        <!-- Main Form Container -->
        <main class="auth-main-container">
            <div class="auth-card-light">
                <!-- Header Icon & Title -->
                <div class="auth-card-top">
                    <div class="header-icon-wrap">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z" fill="currentColor" opacity="0.3"/>
                            <path d="M8 6.5C8 5.12 9.12 4 10.5 4S13 5.12 13 6.5 11.88 9 10.5 9 8 7.88 8 6.5zM15.5 4C14.12 4 13 5.12 13 6.5S14.12 9 15.5 9 18 7.88 18 6.5 16.88 4 15.5 4zM12 10c-3.31 0-6 2.69-6 6h2c0-2.21 1.79-4 4-4s4 1.79 4 4h2c0-3.31-2.69-6-6-6z" fill="currentColor"/>
                        </svg>
                    </div>
                    <h1 class="card-title">{{ title }}</h1>
                    <p class="card-description">{{ description }}</p>
                </div>

                <!-- Slot Content (Form) -->
                <div class="auth-card-body">
                    <slot />
                </div>
            </div>

            <!-- Footer Copy -->
            <footer class="auth-footer-copy">
                © 2026 DigestEdu · Universitas Bina Nusantara
            </footer>
        </main>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

.auth-light-root {
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    min-height: 100vh;
    background-color: #f8fafc;
    color: #0f172a;
    display: flex;
    flex-direction: column;
    position: relative;
    overflow: hidden;
}

/* Glow Blobs */
.auth-light-bg {
    position: fixed;
    inset: 0;
    pointer-events: none;
    z-index: 0;
}
.glow-blob {
    position: absolute;
    border-radius: 50%;
    filter: blur(100px);
}
.blob-top-right {
    width: 450px;
    height: 450px;
    background: radial-gradient(circle, rgba(13, 148, 136, 0.1) 0%, transparent 70%);
    top: -100px;
    right: -100px;
}
.blob-bottom-left {
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(14, 116, 144, 0.08) 0%, transparent 70%);
    bottom: -100px;
    left: -100px;
}

/* Header Navbar */
.auth-header {
    position: relative;
    z-index: 10;
    padding: 16px 32px;
    background-color: #ffffff;
    border-bottom: 1px solid #e2e8f0;
}
.auth-logo-link {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}
.logo-circle-icon {
    width: 32px;
    height: 32px;
    background-color: #0d9488;
    color: #ffffff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.logo-text {
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.4px;
}
.logo-text-accent {
    color: #0d9488;
}

/* Main Container */
.auth-main-container {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 36px 20px;
    position: relative;
    z-index: 1;
}

/* Light Card */
.auth-card-light {
    width: 100%;
    max-width: 440px;
    background-color: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 24px;
    box-shadow: 0 10px 35px rgba(15, 23, 42, 0.05);
    overflow: hidden;
}
.auth-card-top {
    padding: 36px 36px 0;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.header-icon-wrap {
    width: 44px;
    height: 44px;
    background-color: #f0fdf4;
    border: 1px solid #ccfbf1;
    color: #0d9488;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16px;
}
.card-title {
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.4px;
    margin: 0 0 6px;
}
.card-description {
    font-size: 13.5px;
    color: #64748b;
    margin: 0;
    line-height: 1.5;
}
.auth-card-body {
    padding: 28px 36px 36px;
}

/* Footer Copy */
.auth-footer-copy {
    margin-top: 24px;
    font-size: 12.5px;
    color: #94a3b8;
    text-align: center;
}

@media (max-width: 480px) {
    .auth-card-light {
        border-radius: 18px;
    }
    .auth-card-top {
        padding: 28px 24px 0;
    }
    .auth-card-body {
        padding: 20px 24px 28px;
    }
}
</style>
