<script setup lang="ts">
import {
  bookStatusLabel,
  orderStatusLabel,
  paymentStatusLabel,
  reviewStatusLabel,
  statusBadgeColor,
  userStatusLabel,
} from '@/utils/adminLabels'

const props = defineProps<{
  kind: 'book' | 'order' | 'payment' | 'review' | 'user'
  value: string
}>()

function label(): string {
  const v = props.value
  switch (props.kind) {
    case 'book':
      return bookStatusLabel[v as keyof typeof bookStatusLabel] ?? v
    case 'order':
      return orderStatusLabel[v as keyof typeof orderStatusLabel] ?? v
    case 'payment':
      return paymentStatusLabel[v as keyof typeof paymentStatusLabel] ?? v
    case 'review':
      return reviewStatusLabel[v as keyof typeof reviewStatusLabel] ?? v
    case 'user':
      return userStatusLabel[v as keyof typeof userStatusLabel] ?? v
    default:
      return v
  }
}
</script>

<template>
  <CBadge :color="statusBadgeColor(kind, value)">{{ label() }}</CBadge>
</template>
