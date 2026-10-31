<script setup lang="ts">
import '@/assets/styles/customer-auth.css'
import { RouterLink, RouterView } from 'vue-router'
</script>

<template>
  <div class="customer-auth">
    <div class="customer-auth__grid">
      <aside class="customer-auth__hero" aria-hidden="true">
        <div>
          <p class="customer-auth__hero-mark">BookStore</p>
          <p class="customer-auth__hero-tagline">
            Đăng nhập để lưu yêu thích, giỏ hàng và theo dõi đơn hàng của bạn.
          </p>
        </div>
        <p class="customer-auth__hero-quote">“Một cuốn sách hay là người bạn đồng hành lý trí mỗi ngày.”</p>
      </aside>

      <main class="customer-auth__main">
        <div class="customer-auth__main-inner">
          <div class="customer-auth__mobile-brand">
            <RouterLink to="/login" class="customer-auth__hero-mark text-decoration-none">
              BookStore
            </RouterLink>
          </div>
          <RouterView />
        </div>
      </main>
    </div>
  </div>
</template>
