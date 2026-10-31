<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue'
import { useConfirm } from '@/composables/useConfirm'
import { cn } from '@/lib/utils'
import {
  DialogClose,
  DialogContent,
  DialogOverlay,
  DialogPortal,
  DialogRoot,
  DialogTitle,
} from 'reka-ui'

const { visible, title, message, accept, cancel } = useConfirm()
</script>

<template>
  <DialogRoot :open="visible" @update:open="(open) => !open && cancel()">
    <DialogPortal>
      <DialogOverlay class="fixed inset-0 z-50 bg-black/40" />
      <DialogContent
        :class="
          cn(
            'fixed left-1/2 top-1/2 z-50 w-[min(calc(100vw-2rem),24rem)] -translate-x-1/2 -translate-y-1/2 rounded-xl border border-border bg-card p-6 shadow-lg outline-none',
          )
        "
      >
        <DialogTitle class="font-display text-lg font-semibold">{{ title }}</DialogTitle>
        <p class="mt-3 text-sm leading-relaxed text-muted-foreground">{{ message }}</p>
        <div class="mt-6 flex justify-end gap-2">
          <DialogClose as-child>
            <Button type="button" variant="outline" @click="cancel">Hủy</Button>
          </DialogClose>
          <Button type="button" variant="default" class="bg-destructive text-destructive-foreground hover:bg-destructive/90" @click="accept">
            Xác nhận
          </Button>
        </div>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>
