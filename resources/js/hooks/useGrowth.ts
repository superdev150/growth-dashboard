import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { fetchGrowth, type Period } from '../lib/api'

/**
 * The report for a period. Switching periods keeps the previous numbers on screen until the new ones arrive (no
 * flash of empty charts). `refresh` asks the server to skip its cache and writes the answer into the same entry.
 */
export function useGrowth(days: Period) {
  const client = useQueryClient()

  const report = useQuery({
    queryKey: ['growth', days],
    queryFn: ({ signal }) => fetchGrowth(days, false, signal),
    placeholderData: keepPreviousData,
    staleTime: 60_000,
  })

  const refresh = useMutation({
    mutationFn: () => fetchGrowth(days, true),
    onSuccess: (data) => client.setQueryData(['growth', days], data),
  })

  return { report, refresh }
}
