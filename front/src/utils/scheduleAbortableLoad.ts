export function scheduleAbortableLoad(load: (signal: AbortSignal) => void): () => void {
  const controller = new AbortController()
  // Let the current effect pass finish before starting a load that cleanup may discard.
  queueMicrotask(() => {
    if (!controller.signal.aborted) load(controller.signal)
  })

  return () => controller.abort()
}
