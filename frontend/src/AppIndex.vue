<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import ParticipantCard from './components/index/ParticipantCard.vue';
import ReceiptCard from './components/index/ReceiptCard.vue';
import type { CheckinResponse, LookupResponse, SearchItem } from './types';

const scannerValue = ref('');
const loading = ref(false);
const searchText = ref('');
const searchResults = ref<SearchItem[]>([]);

const mode = ref<'idle' | 'participant' | 'receipt' | 'checked_out' | 'error'>('idle');
const errorMessage = ref('');
const participant = ref<LookupResponse | null>(null);
const receipt = ref<CheckinResponse | null>(null);

const scannerRef = ref<HTMLInputElement | null>(null);

let focusTimer: number | null = null;
let resetTimer: number | null = null;
let searchTimer: number | null = null;

function focusScanner(): void {
  scannerRef.value?.focus();
}

function scheduleReset(): void {
  if (resetTimer) window.clearTimeout(resetTimer);
  resetTimer = window.setTimeout(() => {
    mode.value = 'idle';
    participant.value = null;
    receipt.value = null;
    errorMessage.value = '';
  }, 12000);
}

function formatTime(seconds: number): string {
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  return `${h} t ${m} min`;
}

function showError(msg: string): void {
  mode.value = 'error';
  errorMessage.value = msg;
  scheduleReset();
}

async function lookupByQr(qr: string): Promise<void> {
  loading.value = true;
  try {
    const res = await fetch(`lookup.php?qr=${encodeURIComponent(qr)}`);
    const data = (await res.json()) as LookupResponse;

    if (!data.found) {
      showError('Deltaker ikke funnet');
      return;
    }

    participant.value = data;
    mode.value = 'participant';
    scheduleReset();
  } catch {
    showError('Feil ved oppslag');
  } finally {
    loading.value = false;
  }
}

async function onScannerEnter(): Promise<void> {
  const qr = scannerValue.value.trim();
  scannerValue.value = '';
  if (!qr) return;
  await lookupByQr(qr);
}

async function onCheckin(type: 'storage' | 'charging'): Promise<void> {
  if (!participant.value?.qr) return;

  loading.value = true;
  try {
    const res = await fetch(`checkin.php?qr=${encodeURIComponent(participant.value.qr)}&type=${encodeURIComponent(type)}`);
    const data = (await res.json()) as CheckinResponse;

    if (!data.success) {
      if (data.error === 'already_checked_in') {
        showError('Telefon allerede innlevert');
      } else if (data.error === 'no_free_slot') {
        showError('Ingen ledig slot akkurat nå');
      } else {
        showError(`Feil: ${data.error || 'ukjent'}`);
      }
      return;
    }

    receipt.value = data;
    mode.value = 'receipt';
    scheduleReset();
  } catch {
    showError('Feil ved innsjekk');
  } finally {
    loading.value = false;
  }
}

async function onCheckout(sessionId: number): Promise<void> {
  loading.value = true;
  try {
    const res = await fetch(`checkout.php?id=${encodeURIComponent(String(sessionId))}`);
    const data = (await res.json()) as { success: boolean };

    if (!data.success) {
      showError('Kunne ikke registrere utlevering');
      return;
    }

    mode.value = 'checked_out';
    scheduleReset();
  } catch {
    showError('Feil ved utlevering');
  } finally {
    loading.value = false;
  }
}

async function runSearch(): Promise<void> {
  const q = searchText.value.trim();
  if (q.length < 2) {
    searchResults.value = [];
    return;
  }

  try {
    const res = await fetch(`lookup.php?action=search&q=${encodeURIComponent(q)}`);
    const data = (await res.json()) as { items?: SearchItem[] };
    searchResults.value = data.items ?? [];
  } catch {
    searchResults.value = [];
  }
}

function onSearchInput(): void {
  if (searchTimer) window.clearTimeout(searchTimer);
  searchTimer = window.setTimeout(() => {
    void runSearch();
  }, 170);
}

async function pickParticipant(id: number): Promise<void> {
  searchResults.value = [];
  searchText.value = '';

  loading.value = true;
  try {
    const res = await fetch(`lookup.php?participant_id=${encodeURIComponent(String(id))}`);
    const data = (await res.json()) as LookupResponse;

    if (!data.found) {
      showError('Deltaker ikke funnet');
      return;
    }

    participant.value = data;
    mode.value = 'participant';
    scheduleReset();
  } catch {
    showError('Feil ved oppslag');
  } finally {
    loading.value = false;
  }
}

const qrText = (): string => {
  if (!receipt.value) return '';
  return receipt.value.checkout_qr_text || `checkout.php?token=${encodeURIComponent(receipt.value.session_token || '')}`;
};

const qrImageUrl = (): string => `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(qrText())}`;

onMounted(() => {
  focusScanner();
  focusTimer = window.setInterval(focusScanner, 300);
});

onUnmounted(() => {
  if (focusTimer) window.clearInterval(focusTimer);
  if (resetTimer) window.clearTimeout(resetTimer);
  if (searchTimer) window.clearTimeout(searchTimer);
});
</script>

<template>
  <h1>Scan QR-kode</h1>
  <div class="subtitle">eller søk deltakernavn</div>

  <input id="scanner" ref="scannerRef" v-model="scannerValue" autofocus autocomplete="off" @keydown.enter.prevent="onScannerEnter">

  <div class="search-wrap">
    <input
      id="nameSearch"
      v-model="searchText"
      type="search"
      placeholder="Søk navn eller QR (minst 2 tegn)"
      autocomplete="off"
      @input="onSearchInput"
    >
    <div id="nameResults" v-if="searchText.length >= 2">
      <button v-for="item in searchResults" :key="item.id" type="button" class="name-row" @click="pickParticipant(item.id)">
        <strong>{{ item.name || ((item.first_name || '') + ' ' + (item.last_name || '')).trim() }}</strong><br>
        {{ item.county }} - {{ item.participant_type }} - {{ item.qr_code }}
      </button>
      <button v-if="searchResults.length === 0" type="button" class="name-row" disabled>Ingen treff</button>
    </div>
  </div>

  <div id="loading" class="loading" v-show="loading">Laster...</div>

  <div id="result">
    <ParticipantCard
      v-if="mode === 'participant' && participant"
      :participant="participant"
      :loading="loading"
      :format-time="formatTime"
      @checkin="onCheckin"
      @checkout="onCheckout"
    />

    <ReceiptCard
      v-if="mode === 'receipt' && receipt"
      :receipt="receipt"
      :qr-text="qrText()"
      :qr-image-url="qrImageUrl()"
    />

    <div v-if="mode === 'checked_out'" class="slot">Utlevert</div>

    <div v-if="mode === 'error'" class="error">{{ errorMessage }}</div>
  </div>
</template>
