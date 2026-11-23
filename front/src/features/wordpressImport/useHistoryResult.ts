import { useEffect, useState } from 'react'
import { scheduleAbortableLoad } from '../../utils/scheduleAbortableLoad'

type HistoryResult<T> = { data: T } | { error: unknown }

export function useHistoryResult<Input, Result>(load: (input: Input, signal: AbortSignal) => Promise<Result>, input: Input) {
  const [result, setResult] = useState<HistoryResult<Result> | null>(null)
  const [attempt, setAttempt] = useState(0)

  useEffect(() => scheduleAbortableLoad((signal) => {
    load(input, signal).then(
      (data) => { if (!signal.aborted) setResult({ data }) },
      (error: unknown) => { if (!signal.aborted) setResult({ error }) },
    )
  }), [attempt, input, load])

  function retry() {
    setResult(null)
    setAttempt((previous) => previous + 1)
  }

  return { result, retry }
}
