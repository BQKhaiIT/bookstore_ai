/** Chuẩn hóa chuỗi để so khớp tìm kiếm (không phân biệt hoa/thường, có/không dấu tiếng Việt). */
export function normalizeSearchText(input: string): string {
  return input
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/g, 'd')
    .replace(/Đ/g, 'D')
    .toLowerCase()
    .trim()
}

export function matchesSearch(label: string, query: string): boolean {
  const q = normalizeSearchText(query)
  if (!q) return true
  return normalizeSearchText(label).includes(q)
}
