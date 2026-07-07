<script setup>
import { useForm, usePage } from '@inertiajs/vue3';

const page = usePage();
const googleLoginUrl = route('auth.google');
const form = useForm({
    email: '',
});

function submit() {
    form.post(route('auth.magic-link.send'), {
        preserveScroll: true,
        onSuccess: () => form.reset('email'),
    });
}
</script>

<template>
    <div class="login-page">
        <div class="login-card">
            <h1>Log in to PDF Generator</h1>
            <p class="subtitle">Use Google or a magic link — no password needed.</p>

            <a :href="googleLoginUrl" class="google-btn">Continue with Google</a>

            <div class="divider"><span>or</span></div>

            <form @submit.prevent="submit">
                <label for="email">Email address</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    placeholder="you@example.com"
                    autocomplete="email"
                />
                <button type="submit" :disabled="form.processing">Send magic link</button>
                <p v-if="form.errors.email" class="error">{{ form.errors.email }}</p>
            </form>

            <p v-if="page.props.flash?.status" class="status">{{ page.props.flash.status }}</p>
        </div>
    </div>
</template>

<style scoped>
.login-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #FEF9F9;
    padding: 24px;
}

.login-card {
    width: 100%;
    max-width: 360px;
    background: #fff;
    border: 0.5px solid var(--color-border-tertiary, #eee);
    border-radius: 12px;
    padding: 32px;
}

h1 {
    font-size: 20px;
    font-weight: 500;
    margin-bottom: 6px;
}

.subtitle {
    font-size: 13px;
    color: var(--color-text-secondary, #706f6c);
    margin-bottom: 24px;
}

.google-btn {
    display: block;
    width: 100%;
    box-sizing: border-box;
    text-align: center;
    padding: 10px 16px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    color: var(--color-text-primary, #1b1b18);
    text-decoration: none;
}

.google-btn:hover {
    border-color: #E24B4A;
}

.divider {
    display: flex;
    align-items: center;
    text-align: center;
    color: var(--color-text-secondary, #706f6c);
    font-size: 12px;
    margin: 20px 0;
}

.divider::before,
.divider::after {
    content: '';
    flex: 1;
    border-bottom: 0.5px solid #eee;
}

.divider span {
    padding: 0 10px;
}

label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 6px;
}

input {
    width: 100%;
    box-sizing: border-box;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    margin-bottom: 12px;
}

input:focus {
    outline: none;
    border-color: #E24B4A;
}

button {
    width: 100%;
    padding: 10px 16px;
    background: #E24B4A;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
}

button:hover {
    background: #C93B3A;
}

button:disabled {
    opacity: 0.6;
    cursor: default;
}

.error {
    color: #E24B4A;
    font-size: 12px;
    margin-top: -6px;
}

.status {
    margin-top: 16px;
    font-size: 13px;
    color: #3B6D11;
    text-align: center;
}
</style>
