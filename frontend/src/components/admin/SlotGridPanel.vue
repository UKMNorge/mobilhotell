<script setup lang="ts">
import type { SlotDetail, SlotItem } from '../../types';

defineProps<{
  storageSlots: SlotItem[];
  chargingSlots: SlotItem[];
  slotDetail: SlotDetail | null;
  formatDateTime: (value?: string) => string;
}>();

const emit = defineEmits<{
  showSlotDetail: [slotNumber: string];
  setSlotActive: [slotId: number, isActive: 0 | 1];
  manualCheckout: [sessionId: number];
}>();
</script>

<template>
  <h2>Slot-oversikt</h2>
  <h3>Oppbevaring (S-serie)</h3>
  <div id="storageGrid" class="grid">
    <button
      v-for="slot in storageSlots"
      :key="slot.slot_id"
      type="button"
      :class="['slot-btn', slot.status]"
      @click="emit('showSlotDetail', slot.slot_number)"
    >
      {{ slot.slot_number }}
      <span class="slot-mini">{{ slot.name || 'ledig' }}</span>
    </button>
  </div>

  <h3>Lading (L-serie)</h3>
  <div id="chargingGrid" class="grid">
    <button
      v-for="slot in chargingSlots"
      :key="slot.slot_id"
      type="button"
      :class="['slot-btn', slot.status]"
      @click="emit('showSlotDetail', slot.slot_number)"
    >
      {{ slot.slot_number }}
      <span class="slot-mini">{{ slot.name || 'ledig' }}</span>
    </button>
  </div>

  <div id="slotDetail" class="slot-detail">
    <template v-if="slotDetail">
      <div><strong>Slot:</strong> {{ slotDetail.slot_number }}</div>
      <div>
        <strong>Status:</strong>
        {{ slotDetail.status === 'disabled' ? 'Ute av drift' : slotDetail.status === 'busy' ? 'Opptatt' : 'Ledig' }}
      </div>
      <div><strong>Type:</strong> {{ slotDetail.slot_type }}</div>
      <div><strong>Deltaker:</strong> {{ slotDetail.name ? slotDetail.name + ' (' + (slotDetail.qr_code || '-') + ')' : 'Ingen deltaker' }}</div>
      <div><strong>Innsjekk:</strong> {{ formatDateTime(slotDetail.checkin_time) }}</div>
      <div class="actions" style="margin-top:8px;">
        <button
          :class="Number(slotDetail.is_active) === 1 ? 'warn' : 'primary'"
          type="button"
          @click="emit('setSlotActive', slotDetail.slot_id, Number(slotDetail.is_active) === 1 ? 0 : 1)"
        >
          {{ Number(slotDetail.is_active) === 1 ? 'Sett ute av drift' : 'Aktiver slot' }}
        </button>
        <button v-if="slotDetail.session_id" class="danger" type="button" @click="emit('manualCheckout', slotDetail.session_id)">Utlever fra slot</button>
      </div>
    </template>
    <template v-else>
      Klikk på en slot for detaljer.
    </template>
  </div>
</template>