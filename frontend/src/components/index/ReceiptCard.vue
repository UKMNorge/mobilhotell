<script setup lang="ts">
import type { CheckinResponse } from '../../types';

defineProps<{
  receipt: CheckinResponse;
  qrText: string;
  qrImageUrl: string;
}>();
</script>

<template>
  <div class="card">
    <div class="slot">{{ receipt.slot }}</div>
    <div class="info">LEGG TELEFON HER</div>
    <div class="info">{{ receipt.name }}</div>
    <div class="receipt">
      <h2>Kvittering</h2>
      <div class="receipt-line"><strong>Navn:</strong> {{ receipt.name }}</div>
      <div class="receipt-line"><strong>Slot:</strong> {{ receipt.slot }}</div>
      <div class="receipt-line"><strong>Tid:</strong> {{ receipt.checked_in_at || '-' }}</div>
      <div class="qr-box">
        <img :src="qrImageUrl" alt="QR for utlevering" width="180" height="180">
        <div class="qr-text">{{ qrText }}</div>
      </div>
    </div>
  </div>
</template>