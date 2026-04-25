<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import ActiveCheckinsTable from './components/admin/ActiveCheckinsTable.vue';
import SlotGridPanel from './components/admin/SlotGridPanel.vue';
import type { ActiveItem, SlotDetail, SlotItem } from './types';

const searchText = ref('');
const loading = ref(false);
const message = ref('');
const messageType = ref<'success' | 'error' | ''>('');
const autoRefresh = ref(true);

const activeItems = ref<ActiveItem[]>([]);
const slots = ref<SlotItem[]>([]);
const slotDetail = ref<SlotDetail | null>(null);

let pollTimer: number | null = null;
let searchTimer: number | null = null;

const storageSlots = computed(() => slots.value.filter(slot => slot.slot_type === 'storage'));
const chargingSlots = computed(() => slots.value.filter(slot => slot.slot_type === 'charging'));

function setMessage(text: string, type: 'success' | 'error' | '' = ''): void {
  message.value = text;
  messageType.value = type;
}

function formatDateTime(value?: string): string {
  if (!value) return '-';
  const d = new Date(value.replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return value;
  return d.toLocaleString('nb-NO');
}

async function apiGet<T>(action: string, params: Record<string, string> = {}): Promise<T> {
  const query = new URLSearchParams({ action, ...params });
  const res = await fetch(`admin_api.php?${query.toString()}`);
  const data = (await res.json()) as { success: boolean; error?: string } & T;
  if (!res.ok || !data.success) {
    throw new Error(data.error || 'api_error');
  }
  return data;
}

async function apiPost<T>(action: string, payload: Record<string, number>): Promise<T> {
  const res = await fetch(`admin_api.php?action=${encodeURIComponent(action)}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
  const data = (await res.json()) as { success: boolean; error?: string } & T;
  if (!res.ok || !data.success) {
    throw new Error(data.error || 'api_error');
  }
  return data;
}

async function loadActiveList(): Promise<void> {
  const data = await apiGet<{ items: ActiveItem[] }>('active_list', { q: searchText.value.trim() });
  activeItems.value = data.items ?? [];
}

async function loadSlotGrid(): Promise<void> {
  const data = await apiGet<{ items: SlotItem[] }>('slot_grid');
  slots.value = data.items ?? [];
}

async function loadAll(): Promise<void> {
  loading.value = true;
  try {
    await Promise.all([loadActiveList(), loadSlotGrid()]);
    setMessage('Data oppdatert', 'success');
  } catch (err) {
    setMessage(`Kunne ikke hente data: ${(err as Error).message}`, 'error');
  } finally {
    loading.value = false;
  }
}

async function manualCheckout(sessionId: number): Promise<void> {
  loading.value = true;
  try {
    await apiPost('manual_checkout', { session_id: sessionId });
    setMessage('Telefon registrert som utlevert', 'success');
    await loadAll();
  } catch (err) {
    setMessage(`Utlevering feilet: ${(err as Error).message}`, 'error');
  } finally {
    loading.value = false;
  }
}

async function setSlotActive(slotId: number, isActive: 0 | 1): Promise<void> {
  loading.value = true;
  try {
    await apiPost('set_slot_active', { slot_id: slotId, is_active: isActive });
    setMessage(isActive ? 'Slot aktivert' : 'Slot satt ute av drift', 'success');
    await loadAll();
    if (slotDetail.value && slotDetail.value.slot_id === slotId) {
      await showSlotDetail(slotDetail.value.slot_number);
    }
  } catch (err) {
    setMessage(`Kunne ikke oppdatere slot: ${(err as Error).message}`, 'error');
  } finally {
    loading.value = false;
  }
}

async function showSlotDetail(slotNumber: string): Promise<void> {
  try {
    const data = await apiGet<{ slot: SlotDetail }>('slot_detail', { slot_number: slotNumber });
    slotDetail.value = data.slot;
  } catch (err) {
    setMessage(`Kunne ikke hente slot-detalj: ${(err as Error).message}`, 'error');
  }
}

function onSearchInput(): void {
  if (searchTimer) window.clearTimeout(searchTimer);
  searchTimer = window.setTimeout(() => {
    void loadActiveList();
  }, 180);
}

function updateAutoRefresh(enabled: boolean): void {
  autoRefresh.value = enabled;
  if (pollTimer) {
    window.clearInterval(pollTimer);
    pollTimer = null;
  }

  if (enabled) {
    pollTimer = window.setInterval(() => {
      void loadAll();
    }, 7000);
  }
}

onMounted(() => {
  updateAutoRefresh(true);
  void loadAll();
});

onUnmounted(() => {
  if (pollTimer) window.clearInterval(pollTimer);
  if (searchTimer) window.clearTimeout(searchTimer);
});
</script>

<template>
  <header>
    <h1>Mobilhotell Admin</h1>
  </header>

  <main>
    <section class="panel">
      <div class="controls">
        <input id="searchInput" v-model="searchText" type="search" placeholder="Søk på navn eller QR" autocomplete="off" @input="onSearchInput">
        <button id="refreshBtn" class="ghost" type="button" @click="loadAll">Oppdater</button>
        <button id="autoBtn" :class="autoRefresh ? 'primary' : 'ghost'" type="button" @click="updateAutoRefresh(!autoRefresh)">
          Auto oppdater: {{ autoRefresh ? 'PÅ' : 'AV' }}
        </button>
      </div>
      <div id="loading" v-show="loading">Laster data...</div>
      <div id="message" :class="messageType">{{ message }}</div>
    </section>

    <section class="panel">
      <h2>Aktive innleveringer</h2>
      <ActiveCheckinsTable
        :items="activeItems"
        :format-date-time="formatDateTime"
        @manual-checkout="manualCheckout"
        @set-slot-inactive="(slotId) => setSlotActive(slotId, 0)"
      />
    </section>

    <section class="panel">
      <SlotGridPanel
        :storage-slots="storageSlots"
        :charging-slots="chargingSlots"
        :slot-detail="slotDetail"
        :format-date-time="formatDateTime"
        @show-slot-detail="showSlotDetail"
        @set-slot-active="setSlotActive"
        @manual-checkout="manualCheckout"
      />
    </section>
  </main>
</template>
