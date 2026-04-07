<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = useForm({
    csv: null,
});

const isDragging = ref(false);

const validateAndSet = (file) => {
    if (!file) {
        form.csv = null;
        return;
    }
    if (!/\.csv$/i.test(file.name)) {
        form.csv = null;
        form.errors.csv = 'Alleen .csv bestanden worden ondersteund.';
        return;
    }
    form.csv = file;
    form.errors.csv = null;
};

const submit = () => {
    form.post(route('csv-imports.upload'), {
        forceFormData: true,
    });
};

const onFile = (event) => {
    validateAndSet(event.target.files[0] ?? null);
};

const onDrop = (event) => {
    isDragging.value = false;
    validateAndSet(event.dataTransfer.files?.[0] ?? null);
};
</script>

<template>
    <Head title="CSV importeren" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                CSV importeren
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <p class="mb-4 text-sm text-gray-600">
                        Upload een Rabobank CSV-export. De rekening wordt automatisch
                        herkend op basis van het IBAN in het bestand. Dubbele transacties
                        (uit overlappende periodes) worden automatisch overgeslagen.
                    </p>

                    <form @submit.prevent="submit">
                        <label
                            for="csv-input"
                            class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed px-6 py-12 text-center transition focus-within:ring-2 focus-within:ring-indigo-500"
                            :class="isDragging ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 bg-gray-50 hover:bg-gray-100'"
                            @dragover.prevent="isDragging = true"
                            @dragleave.prevent="isDragging = false"
                            @drop.prevent="onDrop"
                        >
                            <svg class="mb-3 h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.9 5 5 0 019.9-1A5.002 5.002 0 0117 16M13 12v6m0 0l-3-3m3 3l3-3" />
                            </svg>
                            <p v-if="form.csv" class="text-sm font-medium text-gray-900">
                                {{ form.csv.name }}
                            </p>
                            <p v-else class="text-sm text-gray-600">
                                Sleep een CSV-bestand hierheen of <span class="font-semibold text-indigo-600">klik om te bladeren</span>
                            </p>
                            <p class="mt-1 text-xs text-gray-500">Rabobank CSV, max 5 MB</p>
                            <input
                                id="csv-input"
                                type="file"
                                accept=".csv,text/csv"
                                class="sr-only"
                                @change="onFile"
                            />
                        </label>
                        <InputError :message="form.errors.csv" class="mt-2" />

                        <div class="mt-6 flex justify-end">
                            <PrimaryButton :disabled="!form.csv || form.processing">
                                Volgende
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
