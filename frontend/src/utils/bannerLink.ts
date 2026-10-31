export function isInternalBannerLink(url: string | null | undefined): boolean {
  if (!url) return false
  const trimmed = url.trim()
  return trimmed.startsWith('/') && !trimmed.startsWith('//')
}

export function bannerLinkTo(url: string): string | { path: string } {
  if (isInternalBannerLink(url)) {
    return { path: url.trim() }
  }
  return url.trim()
}
