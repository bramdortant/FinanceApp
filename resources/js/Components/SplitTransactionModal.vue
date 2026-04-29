<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    transaction: { type: Object, default: null },
    categories: { type: Array, required: true },
});

const emit = defineEmits(['close']);

const form = useForm({
    splits: [],
});

// Total to match: absolute value of the transaction amount.
const total = computed(() => {
    if (!props.transaction) return 0;
    return Math.abs(parseFloat(props.transaction.amount || 0));
});

// Filter categories by transaction type so users can only pick valid ones.
// System categories are also rejected by TransactionSplitRequest, so exclude
// them here too — don't rely on the controller-side filter alone.
const availableCategories = computed(() => {
    if (!props.transaction) return [];
    return props.categories.filter(
        (c) => c.type === props.transaction.type && !c.is_system,
    );
});

const sum = computed(() =>
    form.splits.reduce((acc, s) => acc + (parseFloat(s.amount) || 0), 0)
);

const remaining = computed(() => +(total.value - sum.value).toFixed(2));

const sumStatus = computed(() => {
    const r = remaining.value;
    if (Math.abs(r) < 0.005) return 'match';
    return r < 0 ? 'over' : 'under';
});

const canSubmit = computed(() =>
    form.splits.length >= 2 &&
    sumStatus.value === 'match' &&
    form.splits.every((s) => s.category_id && parseFloat(s.amount) > 0) &&
    !form.processing
);

// Stable client-side identifier so :key on the v-for survives row removals.
// Index keys would let Vue reuse <select>/<TextInput> instances at the same
// position when splits are spliced, leaking transient state to the wrong row.
const newRow = (overrides = {}) => ({
    rowId: crypto.randomUUID(),
    category_id: null,
    amount: '',
    ...overrides,
});

// Whenever the modal opens for a (different) transaction, reset the form
// from existing splits if any, otherwise start with two empty rows.
watch(
    () => [props.show, props.transaction?.id],
    ([show]) => {
        if (!show || !props.transaction) return;
        form.clearErrors();
        const existing = props.transaction.splits || [];
        if (existing.length > 0) {
            form.splits = existing.map((s) => newRow({
                category_id: s.category_id,
                amount: parseFloat(s.amount).toFixed(2),
            }));
        } else {
            form.splits = [
                newRow({ category_id: props.transaction.category_id ?? null }),
                newRow(),
            ];
        }
    },
    { immediate: true }
);

const addRow = () => {
    form.splits.push(newRow());
};

const removeRow = (index) => {
    if (form.splits.length <= 2) return;
    form.splits.splice(index, 1);
};

// Set the last empty (or focused) row's amount to the remaining total.
const fillRemaining = (index) => {
    const r = remaining.value + (parseFloat(form.splits[index].amount) || 0);
    if (r > 0) {
        form.splits[index].amount = r.toFixed(2);
    }
};

// Snap the amount to 2 decimals on blur so what the user sees matches
// what the form will submit (EUR has no sub-cent values).
const roundAmount = (index) => {
    const v = parseFloat(form.splits[index].amount);
    if (!isNaN(v)) {
        form.splits[index].amount = v.toFixed(2);
    }
};

const formatCurrency = (amount) =>
    new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(amount);

const formatDate = (date) => {
    if (!date) return '';
    const [y, m, d] = date.split('T')[0].split('-').map(Number);
    return new Intl.DateTimeFormat('nl-NL', { day: '2-digit', month: 'short', year: 'numeric' })
        .format(new Date(y, m - 1, d));
};

const submit = () => {
    if (!props.transaction) return;
    // Normalize amounts to 2-decimal strings before sending.
    form
        .transform((data) => ({
            splits: data.splits.map((s) => ({
                category_id: s.category_id,
                amount: parseFloat(s.amount).toFixed(2),
            })),
        }))
        .put(route('transactions.splits.update', props.transaction.id), {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        });
};

const removeAllSplits = () => {
    if (!props.transaction) return;
    router.delete(route('transactions.splits.destroy', props.transaction.id), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
};

const hasExistingSplits = computed(() =>
    (props.transaction?.splits?.length ?? 0) > 0
);
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="$emit('close')">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">Transactie splitsen</h2>

            <!-- Read-only original transaction summary -->
            <div v-if="transaction" class="mt-3 rounded-md bg-gray-50 p-3 text-sm">
                <div class="flex items-center justify-between">
                    <div class="font-medium text-gray-900">
                        {{ transaction.description || '—' }}
                    </div>
                    <div
                        class="font-semibold"
                        :class="parseFloat(transaction.amount) >= 0 ? 'text-green-600' : 'text-red-600'"
                    >
                        {{ formatCurrency(transaction.amount) }}
                    </div>
                </div>
                <div class="text-xs text-gray-500">
                    {{ formatDate(transaction.date) }}
                </div>
            </div>

            <!-- Split rows -->
            <div class="mt-4 space-y-2">
                <div
                    v-for="(split, idx) in form.splits"
                    :key="split.rowId"
                    class="grid grid-cols-[1fr_8rem_auto] items-end gap-2"
                >
                    <div>
                        <InputLabel v-if="idx === 0" :for="`split-cat-${idx}`" value="Categorie" />
                        <select
                            :id="`split-cat-${idx}`"
                            v-model="split.category_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required
                        >
                            <option :value="null" disabled>Kies een categorie…</option>
                            <option v-for="cat in availableCategories" :key="cat.id" :value="cat.id">
                                {{ cat.name }}
                            </option>
                        </select>
                        <InputError class="mt-1" :message="form.errors[`splits.${idx}.category_id`]" />
                    </div>
                    <div>
                        <InputLabel v-if="idx === 0" :for="`split-amt-${idx}`" value="Bedrag" />
                        <TextInput
                            :id="`split-amt-${idx}`"
                            v-model="split.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            class="mt-1 block w-full"
                            required
                            @blur="roundAmount(idx)"
                            @dblclick="fillRemaining(idx)"
                        />
                        <InputError class="mt-1" :message="form.errors[`splits.${idx}.amount`]" />
                    </div>
                    <button
                        type="button"
                        class="mb-0.5 px-2 py-1 text-sm text-gray-500 hover:text-red-600 disabled:opacity-30 disabled:cursor-not-allowed"
                        :disabled="form.splits.length <= 2"
                        :title="form.splits.length <= 2 ? 'Minimaal 2 splits' : 'Verwijder regel'"
                        @click="removeRow(idx)"
                    >
                        ✕
                    </button>
                </div>
            </div>

            <button
                type="button"
                class="mt-2 text-sm text-indigo-600 hover:text-indigo-500"
                @click="addRow"
            >
                + Voeg regel toe
            </button>

            <!-- Sum indicator -->
            <div class="mt-3 rounded-md p-2 text-sm" :class="{
                'bg-green-50 text-green-700': sumStatus === 'match',
                'bg-amber-50 text-amber-800': sumStatus === 'under',
                'bg-red-50 text-red-700': sumStatus === 'over',
            }">
                <template v-if="sumStatus === 'match'">
                    ✓ Sluit aan op {{ formatCurrency(total) }}
                </template>
                <template v-else-if="sumStatus === 'under'">
                    {{ formatCurrency(remaining) }} over om te verdelen
                </template>
                <template v-else>
                    {{ formatCurrency(Math.abs(remaining)) }} te veel verdeeld
                </template>
            </div>

            <InputError class="mt-2" :message="form.errors.splits" />

            <!-- Actions -->
            <div class="mt-6 flex justify-between gap-3">
                <DangerButton
                    v-if="hasExistingSplits"
                    type="button"
                    @click="removeAllSplits"
                >
                    Verwijder splits
                </DangerButton>
                <span v-else></span>
                <div class="flex gap-3">
                    <SecondaryButton type="button" @click="$emit('close')">Annuleren</SecondaryButton>
                    <PrimaryButton :disabled="!canSubmit" @click="submit">Opslaan</PrimaryButton>
                </div>
            </div>
        </div>
    </Modal>
</template>
