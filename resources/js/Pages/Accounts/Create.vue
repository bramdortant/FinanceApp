<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    type: 'checking',
    starting_balance: '0.00',
});

const submit = () => {
    form.post(route('accounts.store'));
};
</script>

<template>
    <Head title="Rekening aanmaken" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Rekening aanmaken
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <form @submit.prevent="submit" class="max-w-xl space-y-6">
                        <div>
                            <InputLabel for="name" value="Naam" />
                            <TextInput
                                id="name"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.name"
                                required
                                autofocus
                                placeholder="bijv. Rabobank Betaalrekening"
                            />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div>
                            <InputLabel for="type" value="Type" />
                            <select
                                id="type"
                                v-model="form.type"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="checking">Betaalrekening</option>
                                <option value="savings">Spaarrekening</option>
                                <option value="cash">Contant</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.type" />
                        </div>

                        <div>
                            <InputLabel for="starting_balance" value="Beginsaldo" />
                            <TextInput
                                id="starting_balance"
                                type="number"
                                step="0.01"
                                class="mt-1 block w-full"
                                v-model="form.starting_balance"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.starting_balance" />
                        </div>

                        <div class="flex items-center gap-4">
                            <PrimaryButton :disabled="form.processing">
                                Aanmaken
                            </PrimaryButton>
                            <Link
                                :href="route('accounts.index')"
                                class="text-sm text-gray-600 hover:text-gray-900"
                            >
                                Annuleren
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
