<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import DeleteUserForm from "./Partials/DeleteUserForm.vue";
import UpdatePasswordForm from "./Partials/UpdatePasswordForm.vue";
import UpdateProfileInformationForm from "./Partials/UpdateProfileInformationForm.vue";
import { Head } from "@inertiajs/vue3";

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
});
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <template #title>Profile</template>

        <div class="mx-auto max-w-2xl space-y-5 px-5 py-6">
            <!-- Page heading -->
            <div class="mb-6">
                <h1
                    class="text-xl font-extrabold tracking-tight"
                    style="color: var(--text)"
                >
                    Your Profile
                </h1>
                <p class="mt-1 text-xs" style="color: var(--text-2)">
                    Manage your account details and security settings.
                </p>
            </div>

            <!-- Profile information -->
            <div class="card p-6">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                />
            </div>

            <!-- Password -->
            <div class="card p-6">
                <UpdatePasswordForm />
            </div>

            <!-- Delete account -->
            <div class="card p-6">
                <DeleteUserForm />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
