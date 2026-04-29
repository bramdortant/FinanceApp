<script setup>
import { computed } from 'vue';

const props = defineProps({
    transactions: {
        type: Array,
        required: true,
    },
    showAccount: {
        type: Boolean,
        default: false,
    },
    // When set, the list is rendered from this account's perspective: inbound
    // transfers show the source account name as the counterparty.
    currentAccountId: {
        type: Number,
        default: null,
    },
    // Hide all interactive affordances (edit click, split icon). Used on the
    // "Alle rekeningen" overview, which is read-only by design.
    readOnly: {
        type: Boolean,
        default: false,
    },
    emptyMessage: {
        type: String,
        default: 'Nog geen transacties.',
    },
});

defineEmits(['edit', 'split']);

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('nl-NL', {
        style: 'currency',
        currency: 'EUR',
    }).format(amount);
};

// Parse "YYYY-MM-DD" as a local-calendar date so it doesn't shift across
// timezones the way `new Date('2026-04-07')` (parsed as UTC midnight) does.
const parseLocalDate = (value) => {
    if (!value) return new Date(NaN);
    const [y, m, d] = value.split('T')[0].split('-').map(Number);
    return new Date(y, m - 1, d);
};

const formatDate = (date) => {
    return new Intl.DateTimeFormat('nl-NL', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(parseLocalDate(date));
};

const grouped = computed(() => {
    const groups = {};
    for (const tx of props.transactions) {
        const key = (tx.date || '').split('T')[0];
        if (!groups[key]) groups[key] = [];
        groups[key].push(tx);
    }
    return Object.entries(groups).sort((a, b) => b[0].localeCompare(a[0]));
});
</script>

<template>
    <div v-if="transactions.length === 0" class="p-6 text-center text-sm text-gray-500">
        {{ emptyMessage }}
    </div>
    <div v-else class="divide-y divide-gray-100">
        <div v-for="[date, items] in grouped" :key="date">
            <div class="bg-gray-50 px-4 py-2 text-xs font-medium uppercase tracking-wider text-gray-500">
                {{ formatDate(date) }}
            </div>
            <div v-for="tx in items" :key="tx.id">
                <div
                    class="group relative flex w-full items-center justify-between gap-3 px-4 py-3"
                    :class="{ 'hover:bg-gray-50': !readOnly }"
                >
                    <component
                        :is="readOnly ? 'div' : 'button'"
                        :type="readOnly ? null : 'button'"
                        class="flex min-w-0 flex-1 items-center gap-3 text-left"
                        @click="readOnly ? null : $emit('edit', tx)"
                    >
                        <span
                            class="inline-block h-3 w-3 flex-shrink-0 rounded-full"
                            :style="{ backgroundColor: (tx.splits?.length ? '#9CA3AF' : (tx.category?.color || '#9CA3AF')) }"
                            :aria-label="tx.splits?.length ? 'Gesplitst' : (tx.category?.name || 'Geen categorie')"
                        ></span>
                        <div class="min-w-0">
                            <div class="truncate text-sm font-medium text-gray-900">
                                {{ tx.description || (tx.type === 'transfer' ? 'Overboeking' : '—') }}
                            </div>
                            <div class="truncate text-xs text-gray-500">
                                <template v-if="tx.type === 'transfer'">
                                    ⇄
                                    <template v-if="currentAccountId && tx.transfer_to_account_id === currentAccountId">
                                        {{ tx.account?.name }}
                                    </template>
                                    <template v-else>
                                        {{ tx.transfer_to_account?.name || tx.account?.name }}
                                    </template>
                                </template>
                                <template v-else-if="tx.splits?.length">
                                    <span class="inline-flex rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-indigo-700">
                                        Gesplitst ({{ tx.splits.length }})
                                    </span>
                                </template>
                                <template v-else>
                                    {{ tx.category?.name || 'Geen categorie' }}
                                </template>
                                <span v-if="showAccount && tx.account"> · {{ tx.account.name }}</span>
                            </div>
                        </div>
                    </component>
                    <!-- Split icon: only for non-transfer rows in editable mode. Subtle until hovered. -->
                    <button
                        v-if="!readOnly && tx.type !== 'transfer'"
                        type="button"
                        class="flex-shrink-0 px-2 py-1 text-gray-300 opacity-0 transition hover:text-indigo-600 group-hover:opacity-100"
                        :aria-label="tx.splits?.length ? 'Splits bewerken' : 'Transactie splitsen'"
                        :title="tx.splits?.length ? 'Bewerk splits' : 'Splitsen'"
                        @click.stop="$emit('split', tx)"
                    >
                        ✂
                    </button>
                    <component
                        :is="readOnly ? 'span' : 'button'"
                        :type="readOnly ? null : 'button'"
                        class="flex-shrink-0 text-sm font-semibold"
                        :class="parseFloat(tx.display_amount ?? tx.amount) >= 0 ? 'text-green-600' : 'text-red-600'"
                        @click="readOnly ? null : $emit('edit', tx)"
                    >
                        {{ formatCurrency(tx.display_amount ?? tx.amount) }}
                    </component>
                </div>
                <!-- Always-expanded split rows (indented + muted under parent) -->
                <div
                    v-if="tx.splits?.length"
                    class="border-l-2 border-indigo-100 bg-gray-50/50"
                >
                    <component
                        :is="readOnly ? 'div' : 'button'"
                        v-for="split in tx.splits"
                        :key="split.id"
                        :type="readOnly ? null : 'button'"
                        class="flex w-full items-center justify-between gap-3 py-1.5 pl-12 pr-4 text-left text-xs"
                        :class="{ 'hover:bg-gray-100': !readOnly }"
                        @click="readOnly ? null : $emit('split', tx)"
                    >
                        <div class="flex min-w-0 items-center gap-2">
                            <span
                                class="inline-block h-2.5 w-2.5 flex-shrink-0 rounded-full"
                                :style="{ backgroundColor: split.category?.color || '#9CA3AF' }"
                            ></span>
                            <span class="truncate text-gray-600">{{ split.category?.name || 'Onbekend' }}</span>
                        </div>
                        <span class="flex-shrink-0 text-gray-600">{{ formatCurrency(split.amount) }}</span>
                    </component>
                </div>
            </div>
        </div>
    </div>
</template>
