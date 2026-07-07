<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';

const page = usePage();

function logout() {
    router.post(route('auth.logout'));
}
</script>

<template>
    <div class="app-shell">
        <header class="navbar">
            <Link href="/" class="brand">
                <span class="brand-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                        <path d="M14 2v6h6" />
                    </svg>
                </span>
                <span class="brand-text">PDF Generator</span>
            </Link>

            <nav class="nav-actions">
                <template v-if="page.props.auth?.user">
                    <Link href="/dashboard" class="btn btn-outline">Dashboard</Link>
                    <button type="button" class="btn btn-outline" @click="logout">Log out</button>
                </template>
                <template v-else>
                    <Link href="/login" class="btn btn-outline">Login</Link>
                    <Link href="/login" class="btn btn-filled">Register</Link>
                </template>
            </nav>
        </header>

        <main>
            <slot />
        </main>
    </div>
</template>

<style scoped>
.app-shell {
    min-height: 100vh;
    background: var(--color-background-primary, #fff);
}

.navbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 32px;
    border-bottom: 0.5px solid var(--color-border-tertiary, #ebebe9);
}

.brand {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    color: var(--color-text-primary, #1b1b18);
}

.brand-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: #E24B4A;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
}

.brand-text {
    font-size: 14px;
    font-weight: 500;
}

.nav-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.btn {
    font-size: 14px;
    font-weight: 500;
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    cursor: pointer;
    border: none;
    line-height: 1.2;
}

.btn-outline {
    background: transparent;
    color: var(--color-text-primary, #1b1b18);
    border: 1px solid var(--color-border-tertiary, #ebebe9);
}

.btn-outline:hover {
    border-color: #E24B4A;
}

.btn-filled {
    background: #E24B4A;
    color: #fff;
}

.btn-filled:hover {
    background: #C93B3A;
}

@media (max-width: 480px) {
    .navbar {
        padding: 12px 16px;
    }

    .brand-text {
        display: none;
    }
}
</style>
