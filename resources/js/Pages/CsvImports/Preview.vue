<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref, onMounted, onUnmounted, nextTick, watch } from 'vue';

const props = defineProps({
    token: { type: String, required: true },
    originalFilename: { type: String, required: true },
    sections: { type: Array, required: true },
    categories: { type: Array, required: true },
    transferCategoryId: { type: Number, default: null },
});

const activeTab = ref(0);

// ── Category assignments (hash → category_id) ──────────────────

const categoryAssignments = ref({});

const initAssignments = () => {
    const map = {};
    for (const section of props.sections) {
        for (const row of section.rows) {
            if (row.status === 'duplicate' || row.status === 'transfer_mirror') continue;
            if (row.status === 'transfer' && props.transferCategoryId) {
                map[row.hash] = props.transferCategoryId;
            } else if (row.matched_category_id) {
                map[row.hash] = row.matched_category_id;
            }
        }
    }
    categoryAssignments.value = map;
};
initAssignments();

// ── Row helpers ─────────────────────────────────────────────────

const importableRows = computed(() => {
    const rows = [];
    for (const section of props.sections) {
        for (const row of section.rows) {
            if (row.status !== 'duplicate' && row.status !== 'transfer_mirror') {
                rows.push(row);
            }
        }
    }
    return rows;
});

const uncategorizedRows = computed(() =>
    importableRows.value.filter(r => !categoryAssignments.value[r.hash])
);

const allCategorized = computed(() =>
    uncategorizedRows.value.length === 0 && importableRows.value.length > 0
);

const currentSectionRows = computed(() =>
    props.sections[activeTab.value]?.rows ?? []
);

// ── Active row & navigation ─────────────────────────────────────

const activeRowHash = ref(null);

const goToNextUncategorized = () => {
    if (uncategorizedRows.value.length > 0) {
        activeRowHash.value = uncategorizedRows.value[0].hash;
        scrollActiveRowIntoView();
    } else {
        activeRowHash.value = null;
    }
};

const scrollActiveRowIntoView = () => {
    nextTick(() => {
        const el = document.querySelector(`[data-hash="${activeRowHash.value}"]`);
        if (el) el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    });
};

// ── Paint mode ──────────────────────────────────────────────────

const paintMode = ref(false);
const paintCategoryId = ref(null);
const paintIsDragging = ref(false);

const paintCategoryName = computed(() => {
    if (!paintCategoryId.value) return null;
    const cat = props.categories.find(c => c.id === paintCategoryId.value);
    return cat?.name ?? null;
});

const paintCategoryColor = computed(() => {
    if (!paintCategoryId.value) return '#6B7280';
    const cat = props.categories.find(c => c.id === paintCategoryId.value);
    return cat?.color ?? '#6B7280';
});

const togglePaintMode = () => {
    paintMode.value = !paintMode.value;
    if (!paintMode.value) {
        paintCategoryId.value = null;
        paintIsDragging.value = false;
    }
};

const paintRow = (row) => {
    if (!paintMode.value || !paintCategoryId.value) return;
    if (row.status === 'transfer' || row.status === 'duplicate' || row.status === 'transfer_mirror') return;
    categoryAssignments.value[row.hash] = paintCategoryId.value;
};

const onRowMouseDown = (row, event) => {
    if (paintMode.value && paintCategoryId.value) {
        paintIsDragging.value = true;
        paintRow(row);
        event.preventDefault();
    }
};

const onRowMouseEnter = (row) => {
    if (paintIsDragging.value) {
        paintRow(row);
    }
};

const onMouseUp = () => {
    paintIsDragging.value = false;
};

// ── Category picker ─────────────────────────────────────────────

const pickerOpenForHash = ref(null);
const categoryType = ref('expense');
const pickerStyle = ref({});
const pickerSearch = ref('');
const pickerSearchInput = ref(null);

const openPicker = (row, event) => {
    if (paintMode.value) return; // In paint mode, clicks paint — don't open picker.
    if (row.status === 'transfer' || row.status === 'duplicate' || row.status === 'transfer_mirror') return;
    activeRowHash.value = row.hash;
    categoryType.value = parseFloat(row.amount) >= 0 ? 'income' : 'expense';
    pickerSearch.value = '';
    pickerOpenForHash.value = row.hash;

    if (event) {
        const tr = event.currentTarget;
        const cells = tr.querySelectorAll('td');
        const categoryCell = cells[4] || tr;
        const rect = categoryCell.getBoundingClientRect();
        const pickerWidth = 288;
        const pickerHeight = 320;
        const spaceBelow = window.innerHeight - rect.bottom;

        pickerStyle.value = {
            position: 'fixed',
            left: Math.min(rect.left, window.innerWidth - pickerWidth - 8) + 'px',
            top: spaceBelow > pickerHeight
                ? rect.bottom + 4 + 'px'
                : (rect.top - pickerHeight - 4) + 'px',
            width: pickerWidth + 'px',
            zIndex: 50,
        };
    }

    nextTick(() => pickerSearchInput.value?.focus());
};

const filteredPickerCategories = computed(() => {
    let cats = props.categories.filter(c => c.type === categoryType.value);
    if (pickerSearch.value) {
        const q = pickerSearch.value.toLowerCase();
        cats = cats.filter(c => c.name.toLowerCase().includes(q));
    }
    return cats;
});

const assignCategory = (hash, categoryId) => {
    categoryAssignments.value[hash] = categoryId;
    pickerOpenForHash.value = null;
    pickerSearch.value = '';

    // Auto-advance to next uncategorized row.
    nextTick(() => goToNextUncategorized());
};

const closePicker = () => {
    if (pickerOpenForHash.value) {
        pickerOpenForHash.value = null;
        pickerSearch.value = '';
    }
};

// ── Keyboard handling ───────────────────────────────────────────

const handleKeydown = (e) => {
    // When picker is open: number keys, search typing, escape, enter.
    if (pickerOpenForHash.value) {
        if (e.key === 'Escape') {
            e.preventDefault();
            closePicker();
            return;
        }

        // Enter selects the first visible category (useful after filtering).
        if (e.key === 'Enter') {
            e.preventDefault();
            const cat = filteredPickerCategories.value[0];
            if (cat) assignCategory(pickerOpenForHash.value, cat.id);
            return;
        }

        // Number keys 1-9, 0 to pick from visible list.
        const num = parseInt(e.key);
        if (!isNaN(num) && !pickerSearch.value) {
            const idx = num === 0 ? 9 : num - 1;
            const cat = filteredPickerCategories.value[idx];
            if (cat) {
                e.preventDefault();
                assignCategory(pickerOpenForHash.value, cat.id);
            }
            return;
        }

        // All other keys go to the search input (handled by the input itself).
        return;
    }

    // When picker is closed:

    // Tab = jump to next uncategorized row and open picker.
    if (e.key === 'Tab' && !e.shiftKey) {
        if (uncategorizedRows.value.length > 0) {
            e.preventDefault();
            const row = uncategorizedRows.value[0];
            activeRowHash.value = row.hash;
            scrollActiveRowIntoView();
            // Open picker with position calculated from the DOM row.
            nextTick(() => {
                const el = document.querySelector(`[data-hash="${row.hash}"]`);
                if (el) openPicker(row, { currentTarget: el });
            });
        }
        return;
    }

    // Shift+Tab = jump to previous uncategorized row.
    if (e.key === 'Tab' && e.shiftKey) {
        if (uncategorizedRows.value.length > 0) {
            e.preventDefault();
            const row = uncategorizedRows.value[uncategorizedRows.value.length - 1];
            activeRowHash.value = row.hash;
            scrollActiveRowIntoView();
            nextTick(() => {
                const el = document.querySelector(`[data-hash="${row.hash}"]`);
                if (el) openPicker(row, { currentTarget: el });
            });
        }
        return;
    }

    // Arrow keys navigate ALL rows (including assigned ones).
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        const rows = currentSectionRows.value;
        if (!rows.length) return;
        const currentIdx = rows.findIndex(r => r.hash === activeRowHash.value);
        let nextIdx;
        if (e.key === 'ArrowDown') {
            nextIdx = currentIdx < rows.length - 1 ? currentIdx + 1 : 0;
        } else {
            nextIdx = currentIdx > 0 ? currentIdx - 1 : rows.length - 1;
        }
        activeRowHash.value = rows[nextIdx].hash;
        scrollActiveRowIntoView();
        return;
    }

    // Enter opens picker on active row.
    if (e.key === 'Enter' && activeRowHash.value) {
        e.preventDefault();
        const row = importableRows.value.find(r => r.hash === activeRowHash.value);
        if (row) {
            const el = document.querySelector(`[data-hash="${row.hash}"]`);
            openPicker(row, el ? { currentTarget: el } : null);
        }
    }
};

onMounted(() => {
    document.addEventListener('keydown', handleKeydown);
    document.addEventListener('mousedown', closePicker);
    document.addEventListener('mouseup', onMouseUp);
    goToNextUncategorized();
});
onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
    document.removeEventListener('mousedown', closePicker);
    document.removeEventListener('mouseup', onMouseUp);
});

// ── Form submission ─────────────────────────────────────────────

const form = useForm({
    token: props.token,
    categories: {},
});

const submit = () => {
    form.categories = { ...categoryAssignments.value };
    form.post(route('csv-imports.store'));
};

// ── Formatting helpers ──────────────────────────────────────────

const totals = computed(() => {
    const t = { new: 0, transfer: 0, mirror: 0, duplicate: 0 };
    for (const s of props.sections) {
        t.new += s.summary.new;
        t.transfer += s.summary.transfer;
        t.mirror += s.summary.mirror || 0;
        t.duplicate += s.summary.duplicate;
    }
    return t;
});

const formatCurrency = (amount) =>
    new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(amount);

const formatDate = (date) => {
    if (!date) return '';
    const parts = date.split('-');
    if (parts.length !== 3) return date;
    const [y, m, d] = parts.map(Number);
    const parsed = new Date(y, m - 1, d);
    if (isNaN(parsed.getTime())) return date;
    return new Intl.DateTimeFormat('nl-NL', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(parsed);
};

const statusBadge = (status) => {
    if (status === 'duplicate') return { label: 'Duplicaat', class: 'bg-gray-200 text-gray-700' };
    if (status === 'transfer') return { label: 'Overboeking', class: 'bg-blue-100 text-blue-700' };
    if (status === 'transfer_mirror') return { label: 'Spiegel (skip)', class: 'bg-gray-200 text-gray-500' };
    return { label: 'Nieuw', class: 'bg-green-100 text-green-700' };
};

const getCategoryName = (hash) => {
    const id = categoryAssignments.value[hash];
    if (!id) return null;
    if (id === props.transferCategoryId) return 'Overboeking';
    const cat = props.categories.find(c => c.id === id);
    return cat?.name ?? null;
};

const getCategoryColor = (hash) => {
    const id = categoryAssignments.value[hash];
    if (!id || id === props.transferCategoryId) return '#9CA3AF';
    const cat = props.categories.find(c => c.id === id);
    return cat?.color ?? '#6B7280';
};
</script>

<template>
    <Head title="CSV preview" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                CSV preview
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Summary tiles -->
                <div class="mb-4 grid grid-cols-4 gap-4">
                    <div class="rounded-md bg-green-50 p-4 text-center">
                        <div class="text-2xl font-bold text-green-700">{{ totals.new }}</div>
                        <div class="text-xs text-green-700">Nieuw</div>
                    </div>
                    <div class="rounded-md bg-blue-50 p-4 text-center">
                        <div class="text-2xl font-bold text-blue-700">{{ totals.transfer }}</div>
                        <div class="text-xs text-blue-700">Overboekingen</div>
                    </div>
                    <div class="rounded-md bg-gray-100 p-4 text-center">
                        <div class="text-2xl font-bold text-gray-700">{{ totals.mirror }}</div>
                        <div class="text-xs text-gray-700">Spiegelregels (skip)</div>
                    </div>
                    <div class="rounded-md bg-gray-100 p-4 text-center">
                        <div class="text-2xl font-bold text-gray-700">{{ totals.duplicate }}</div>
                        <div class="text-xs text-gray-700">Duplicaten (skip)</div>
                    </div>
                </div>

                <!-- Uncategorized counter + paint mode toggle -->
                <div class="mb-4 flex items-center justify-between gap-4">
                    <div v-if="uncategorizedRows.length > 0" class="flex-1 rounded-md bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800">
                        <strong>{{ uncategorizedRows.length }}</strong> {{ uncategorizedRows.length === 1 ? 'transactie heeft' : 'transacties hebben' }} nog geen categorie.
                        <span v-if="!paintMode">
                            Klik op een rij of druk op <kbd class="rounded bg-amber-200 px-1.5 py-0.5 text-xs font-mono">Tab</kbd> om te beginnen.
                        </span>
                    </div>
                    <div v-else class="flex-1 rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800">
                        Alle transacties hebben een categorie.
                    </div>

                    <button
                        type="button"
                        class="shrink-0 rounded-md px-3 py-2 text-sm font-medium transition"
                        :class="paintMode
                            ? 'bg-indigo-600 text-white hover:bg-indigo-700'
                            : 'bg-white text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50'"
                        @click="togglePaintMode"
                    >
                        🎨 Verfmodus
                    </button>
                </div>

                <!-- Paint mode category palette -->
                <div v-if="paintMode" class="mb-4 rounded-md border border-indigo-200 bg-indigo-50 p-3">
                    <p class="mb-2 text-xs text-indigo-700">Kies een categorie en klik (of sleep) over rijen om ze toe te wijzen:</p>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="cat in categories.filter(c => c.type === 'expense')"
                            :key="cat.id"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium transition"
                            :class="paintCategoryId === cat.id
                                ? 'ring-2 ring-indigo-500 ring-offset-1 bg-white text-gray-900'
                                : 'bg-white text-gray-600 hover:bg-gray-100'"
                            @click="paintCategoryId = cat.id"
                            @mousedown.stop
                        >
                            <span
                                class="inline-block h-2.5 w-2.5 rounded-full"
                                :style="{ backgroundColor: cat.color }"
                            ></span>
                            {{ cat.name }}
                        </button>
                    </div>
                    <div v-if="paintCategoryId" class="mt-2 text-xs text-indigo-600">
                        Actief: <strong>{{ paintCategoryName }}</strong> — klik of sleep over rijen.
                    </div>
                </div>

                <!-- Account tabs -->
                <div class="mb-4 flex gap-2 border-b border-gray-200">
                    <button
                        v-for="(section, idx) in sections"
                        :key="section.account.id"
                        type="button"
                        class="border-b-2 px-4 py-2 text-sm font-medium transition"
                        :class="activeTab === idx
                            ? 'border-indigo-500 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700'"
                        @click="activeTab = idx"
                    >
                        {{ section.account.name }}
                        <span class="ml-2 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
                            {{ section.summary.new }}
                        </span>
                    </button>
                </div>

                <!-- Transaction table -->
                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Datum</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Omschrijving</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Tegenpartij</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Categorie</th>
                                <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Bedrag</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <tr v-if="!sections.length || !sections[activeTab]">
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">
                                    Geen transacties om te tonen.
                                </td>
                            </tr>
                            <tr
                                v-else
                                v-for="(row, idx) in sections[activeTab].rows"
                                :key="idx"
                                :data-hash="row.hash"
                                :class="[
                                    (row.status === 'duplicate' || row.status === 'transfer_mirror') ? 'opacity-50' : '',
                                    activeRowHash === row.hash ? 'bg-indigo-50 ring-2 ring-inset ring-indigo-300' : '',
                                    (row.status !== 'duplicate' && row.status !== 'transfer_mirror' && !categoryAssignments[row.hash]) ? 'bg-amber-50' : '',
                                    paintMode && paintCategoryId ? 'select-none' : '',
                                ]"
                                class="cursor-pointer transition-colors"
                                @mousedown.stop="onRowMouseDown(row, $event)"
                                @mouseenter="onRowMouseEnter(row)"
                                @click="openPicker(row, $event)"
                            >
                                <td class="whitespace-nowrap px-4 py-2">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                        :class="statusBadge(row.status).class"
                                    >
                                        {{ statusBadge(row.status).label }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-sm text-gray-700">
                                    {{ formatDate(row.date) }}
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-900">
                                    <div class="truncate max-w-xs" :title="row.description">{{ row.description || '—' }}</div>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500">
                                    <div v-if="row.transfer_to_account_name" class="whitespace-nowrap text-blue-700">
                                        ⇄ {{ row.transfer_to_account_name }}
                                    </div>
                                    <div v-else class="truncate max-w-[12rem]" :title="row.counterparty_name || ''">
                                        {{ row.counterparty_name || '—' }}
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-sm">
                                    <div v-if="row.status === 'duplicate' || row.status === 'transfer_mirror'" class="text-gray-400">
                                        —
                                    </div>
                                    <div v-else-if="categoryAssignments[row.hash]" class="flex items-center gap-1.5">
                                        <span
                                            class="inline-block h-3 w-3 rounded-full border border-gray-200"
                                            :style="{ backgroundColor: getCategoryColor(row.hash) }"
                                        ></span>
                                        <span class="text-gray-700">{{ getCategoryName(row.hash) }}</span>
                                    </div>
                                    <div v-else class="text-amber-600 font-medium">
                                        Kies…
                                    </div>
                                </td>
                                <td
                                    class="whitespace-nowrap px-4 py-2 pr-6 text-right text-sm font-semibold"
                                    :class="parseFloat(row.amount) >= 0 ? 'text-green-600' : 'text-red-600'"
                                >
                                    {{ formatCurrency(row.amount) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Errors -->
                <div v-if="Object.keys(form.errors).length" class="mt-4 rounded bg-red-50 p-3 text-sm text-red-700">
                    <p v-for="(error, field) in form.errors" :key="field">{{ error }}</p>
                </div>

                <!-- Action buttons -->
                <div class="mt-6 flex justify-end gap-3">
                    <Link :href="route('csv-imports.create')">
                        <SecondaryButton type="button">Annuleren</SecondaryButton>
                    </Link>
                    <form @submit.prevent="submit">
                        <PrimaryButton :disabled="form.processing || !allCategorized">
                            Bevestig import ({{ totals.new }} nieuw)
                        </PrimaryButton>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Category picker (Teleport to body to avoid overflow clipping) -->
    <Teleport to="body">
        <div
            v-if="pickerOpenForHash"
            class="rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5"
            :style="pickerStyle"
            @click.stop
            @mousedown.stop
        >
            <!-- Search filter -->
            <div class="border-b border-gray-100 p-2">
                <input
                    ref="pickerSearchInput"
                    v-model="pickerSearch"
                    type="text"
                    class="w-full rounded border-gray-300 px-2 py-1 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="Zoek categorie…"
                    @keydown.stop
                />
            </div>
            <div class="max-h-60 overflow-y-auto py-1">
                <button
                    v-for="(cat, catIdx) in filteredPickerCategories"
                    :key="cat.id"
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-gray-100"
                    @click="assignCategory(pickerOpenForHash, cat.id)"
                >
                    <span class="inline-flex h-4 w-4 items-center justify-center rounded text-xs font-mono text-gray-500 bg-gray-100">
                        {{ catIdx < 9 ? catIdx + 1 : catIdx === 9 ? 0 : '' }}
                    </span>
                    <span
                        class="inline-block h-3 w-3 rounded-full border border-gray-200"
                        :style="{ backgroundColor: cat.color }"
                    ></span>
                    <span>{{ cat.name }}</span>
                </button>
                <div v-if="filteredPickerCategories.length === 0" class="px-3 py-2 text-sm text-gray-500">
                    Geen categorieën gevonden.
                </div>
            </div>
        </div>
    </Teleport>
</template>
