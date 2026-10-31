<script setup lang="ts">
import CustomerContainer from '@/components/customer/CustomerContainer.vue'
import Separator from '@/components/ui/separator/Separator.vue'
import { RouterLink } from 'vue-router'
</script>

<template>
  <footer class="mt-auto border-t border-border bg-card">
    <CustomerContainer class="py-12">
      <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
        <div class="lg:col-span-1">
          <p class="font-display text-lg font-semibold">BookStore</p>
          <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
            Nhà sách trực tuyến — chọn sách, đặt hàng và nhận tại nhà với thanh toán COD.
          </p>
        </div>
        <div>
          <p class="text-sm font-semibold">Mua sắm</p>
          <ul class="mt-3 space-y-2 text-sm text-muted-foreground">
            <li><RouterLink to="/books" class="hover:text-foreground">Danh mục sách</RouterLink></li>
            <li><RouterLink to="/cart" class="hover:text-foreground">Giỏ hàng</RouterLink></li>
            <li><RouterLink to="/wishlist" class="hover:text-foreground">Yêu thích</RouterLink></li>
          </ul>
        </div>
        <div>
          <p class="text-sm font-semibold">Tài khoản</p>
          <ul class="mt-3 space-y-2 text-sm text-muted-foreground">
            <li><RouterLink to="/login" class="hover:text-foreground">Đăng nhập</RouterLink></li>
            <li><RouterLink to="/register" class="hover:text-foreground">Đăng ký</RouterLink></li>
            <li><RouterLink to="/account" class="hover:text-foreground">Hồ sơ</RouterLink></li>
            <li><RouterLink to="/orders" class="hover:text-foreground">Đơn hàng</RouterLink></li>
          </ul>
        </div>
        <div>
          <p class="text-sm font-semibold">Thông tin</p>
          <ul class="mt-3 space-y-2 text-sm text-muted-foreground">
            <li>Thanh toán khi nhận hàng (COD)</li>
            <li>Địa chỉ giao hàng lưu trong tài khoản</li>
            <li>Chính sách đổi trả: theo quy định tại thời điểm giao hàng</li>
          </ul>
        </div>
      </div>
      <Separator class="my-8" />
      <p class="text-xs text-muted-foreground">© {{ new Date().getFullYear() }} BookStore</p>
    </CustomerContainer>
  </footer>
</template>
