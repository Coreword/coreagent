<script setup>
import { useForm, Link } from '@inertiajs/vue3';

defineProps({
    show: Boolean,
});
const emit = defineEmits(['close']);

const form = useForm({ email: '', password: '', remember: false });

function submit() {
    form.post(route('login'), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <div v-if="show" class="modal-backdrop" @click.self="$emit('close')">
        <div class="modal-frame" style="max-width: 400px;">
            <button type="button" class="modal-close" @click="$emit('close')">✕</button>
            <div class="modal-heading">
                <h2>Sign in to coreAgent</h2>
                <p>Sign in to save your work, create a project, and let coreAgent get to work.</p>
            </div>
            <form class="form-stack" @submit.prevent="submit">
                <label>
                    <span>Email</span>
                    <input v-model="form.email" type="email" required autofocus autocomplete="username" />
                </label>
                <label>
                    <span>Password</span>
                    <input v-model="form.password" type="password" required autocomplete="current-password" />
                </label>
                <p v-if="form.errors.email" style="margin: 0; color: #c2410c; font-size: 10px;">{{ form.errors.email }}</p>
                <label style="display: flex; flex-direction: row; align-items: center; gap: 6px;">
                    <input v-model="form.remember" type="checkbox" style="width: auto;" />
                    <span style="font-weight: 600;">Remember me</span>
                </label>
            </form>
            <div class="modal-footer" style="justify-content: space-between; align-items: center;">
                <Link :href="route('password.request')" style="font-size: 10px; color: var(--cw-muted);">Forgot your password?</Link>
                <button type="button" class="app-button button-primary" :disabled="form.processing" @click="submit">Log in</button>
            </div>
            <p style="margin: 14px 0 0; color: var(--cw-muted); font-size: 10px; text-align: center;">
                Don't have an account? <Link :href="route('register')" style="color: var(--cw-blue-strong); font-weight: 700;">Register</Link>
            </p>
        </div>
    </div>
</template>
