export function validatePassword(password: string): string | undefined {
  if (password.length < 8) {
    return 'Mật khẩu phải có ít nhất 8 ký tự.'
  }
  if (!/[a-z]/.test(password) || !/[A-Z]/.test(password)) {
    return 'Mật khẩu phải có chữ hoa và chữ thường.'
  }
  if (!/[0-9]/.test(password)) {
    return 'Mật khẩu phải có ít nhất một chữ số.'
  }

  return undefined
}

export function validateEmail(email: string): string | undefined {
  if (!email.trim()) {
    return 'Vui lòng nhập email.'
  }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    return 'Email không hợp lệ.'
  }

  return undefined
}
