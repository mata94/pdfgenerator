<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="banner">
        <p class="message">You've used all 3 free conversions. Log in to keep converting.</p>
        <Link href="/login" class="btn">Log in</Link>
    </div>
</template>

<style scoped>
.banner {
    max-width: 560px;
    margin: 16px auto 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    background: #FCEBEB;
    border: 0.5px solid #E24B4A;
    border-radius: var(--border-radius-lg, 12px);
    padding: 14px 18px;
    flex-wrap: wrap;
}

.message {
    font-size: 13px;
    color: var(--color-text-primary, #1b1b18);
}

.btn {
    flex-shrink: 0;
    background: #E24B4A;
    color: #fff;
    font-size: 13px;
    font-weight: 500;
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
}

.btn:hover {
    background: #C93B3A;
}
</style>
