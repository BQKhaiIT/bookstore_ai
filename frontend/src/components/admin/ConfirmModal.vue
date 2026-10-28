<script setup lang="ts">
import { useConfirm } from '@/composables/useConfirm'

const { visible, title, message, accept, cancel } = useConfirm()
</script>

<template>
  <CModal class="admin-modal" :visible="visible" alignment="center" @close="cancel">
    <CModalHeader>
      <CModalTitle>{{ title }}</CModalTitle>
    </CModalHeader>
    <CModalBody>{{ message }}</CModalBody>
    <CModalFooter>
      <CButton color="secondary" @click="cancel">Hủy</CButton>
      <CButton color="danger" @click="accept">Xác nhận</CButton>
    </CModalFooter>
  </CModal>
</template>
