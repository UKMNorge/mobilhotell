<script setup lang="ts">
import type { LookupResponse } from '../../types';

defineProps<{
  participant: LookupResponse;
  loading: boolean;
  formatTime: (seconds: number) => string;
}>();

const emit = defineEmits<{
  checkin: [type: 'storage' | 'charging'];
  checkout: [sessionId: number];
}>();
</script>

<template>
  <div class="card">
    <div class="name">{{ participant.name }}</div>
    <div class="info">{{ participant.county }}</div>
    <div class="info">{{ participant.type }}</div>
    <div class="info">Skjermfri tid: {{ formatTime(Number(participant.screenfree_seconds || 0)) }}</div>

    <template v-if="participant.checked_in && participant.session_id">
      <div class="slot">Slot {{ participant.slot }}</div>
      <button :disabled="loading" @click="emit('checkout', participant.session_id)">Registrer utlevert</button>
    </template>

    <template v-else>
      <button :disabled="loading" @click="emit('checkin', 'storage')">Oppbevar</button>
      <button :disabled="loading" @click="emit('checkin', 'charging')">Lad</button>
    </template>
  </div>
</template>