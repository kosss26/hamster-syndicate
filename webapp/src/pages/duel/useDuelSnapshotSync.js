import { useCallback, useRef } from 'react'

export function useDuelSnapshotSync({
  getDuel,
  applySnapshotRef,
  onSyncErrorRef,
}) {
  const duelSyncSeqRef = useRef(0)
  const duelSyncAppliedSeqRef = useRef(0)
  const duelSyncInFlightRef = useRef(false)
  const duelSyncPendingRequestRef = useRef(null)

  const nextDuelSyncSeq = () => {
    duelSyncSeqRef.current += 1
    return duelSyncSeqRef.current
  }

  const isStaleDuelSyncSeq = (seq) => seq < duelSyncAppliedSeqRef.current

  const markDuelSyncApplied = (seq) => {
    if (seq > duelSyncAppliedSeqRef.current) {
      duelSyncAppliedSeqRef.current = seq
    }
  }

  const syncDuelSnapshot = useCallback(async (duelId, options = {}) => {
    if (!duelId) return

    if (duelSyncInFlightRef.current) {
      duelSyncPendingRequestRef.current = { duelId, options }
      return
    }

    duelSyncInFlightRef.current = true

    const { mergeDuel = false, source = 'duel sync' } = options
    const syncSeq = nextDuelSyncSeq()
    try {
      const response = await getDuel(duelId)
      if (!response?.success) return

      if (isStaleDuelSyncSeq(syncSeq)) return
      markDuelSyncApplied(syncSeq)

      if (typeof applySnapshotRef.current === 'function') {
        applySnapshotRef.current(response.data, { mergeDuel, duelIdOverride: duelId })
      }
    } catch (err) {
      if (isStaleDuelSyncSeq(syncSeq)) return
      console.error(`Failed to ${source}:`, err)
      if (typeof onSyncErrorRef.current === 'function') {
        onSyncErrorRef.current(err)
      }
    } finally {
      duelSyncInFlightRef.current = false

      const pending = duelSyncPendingRequestRef.current
      if (pending && pending.duelId) {
        duelSyncPendingRequestRef.current = null
        void syncDuelSnapshot(pending.duelId, pending.options || {})
      }
    }
  }, [getDuel, applySnapshotRef, onSyncErrorRef])

  return {
    syncDuelSnapshot,
  }
}
