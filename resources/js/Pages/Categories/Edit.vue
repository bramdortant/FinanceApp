<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    category: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    name: props.category.name,
    type: props.category.type || 'expense',
    color: props.category.color || '#6B7280',
});

const submit = () => {
    form.put(route('categories.update', props.category.id));
};
</script>

<template>
    <Head title="Categorie bewerken" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Categorie bewerken
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
                                <option value="expense">Uitgaven</option>
                                <option value="income">Inkomsten</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.type" />
                        </div>

                        <div>
                            <InputLabel for="color" value="Kleur" />
                            <div class="mt-1 flex items-center gap-3">
                                <input
                                    id="color"
                                    type="color"
                                    v-model="form.color"
                                    class="h-10 w-14 cursor-pointer rounded-md border border-gray-300"
                                />
                                <TextInput
                                    type="text"
                                    class="w-28"
                                    v-model="form.color"
                                    maxlength="7"
                                    placeholder="#6B7280"
                                />
                            </div>
                            <InputError class="mt-2" :message="form.errors.color" />
                        </div>

                        <div class="flex items-center gap-4">
                            <PrimaryButton :disabled="form.processing">
                                Opslaan
                            </PrimaryButton>
                            <Link
                                :href="route('categories.index')"
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
