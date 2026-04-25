<script setup lang="ts">
import type { ActiveItem } from '../../types';

defineProps<{
  items: ActiveItem[];
  formatDateTime: (value?: string) => string;
}>();

const emit = defineEmits<{
  manualCheckout: [sessionId: number];
  setSlotInactive: [slotId: number];
}>();
</script>

<template>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Navn</th>
          <th>QR</th>
          <th>Slot</th>
          <th>Type</th>
          <th>Tidspunkt</th>
          <th>Handling</th>
        </tr>
      </thead>
      <tbody id="activeTableBody">
        <tr v-if="items.length === 0">
          <td colspan="6">Ingen aktive innleveringer</td>
        </tr>
        <tr v-for="item in items" :key="item.session_id">
          <td>{{ item.name }}</td>
          <td>{{ item.qr_code }}</td>
          <td><span class="slot-badge">{{ item.slot_number }}</span></td>
          <td>{{ item.slot_type }}</td>
          <td>{{ formatDateTime(item.checkin_time) }}</td>
          <td>
            <div class="actions">
              <button class="danger" type="button" @click="emit('manualCheckout', item.session_id)">Utlever</button>
              <button class="warn" type="button" @click="emit('setSlotInactive', item.slot_id)">Ute av drift</button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>