import { describe, expect, it } from 'vitest'
import { readDraftLink } from './draftLink'

const draft = '0199b348-1d8f-7e2f-8672-9b8356792e42'
const type = '0199b348-1d8f-7e2f-8672-9b8356792e43'

describe('readDraftLink', () => {
  it('leaves the normal workspace active without an explicit draft parameter', () => {
    expect(readDraftLink(`?type=${type}`)).toBeNull()
  })

  it('reads and normalizes page and entry UUIDs', () => {
    expect(readDraftLink(`?draft=${draft.toUpperCase()}`)).toEqual({ id: draft, contentTypeId: null })
    expect(readDraftLink(`?type=${type}&draft=${draft}`)).toEqual({ id: draft, contentTypeId: type })
  })

  it('rejects malformed or ambiguous identifiers', () => {
    expect(readDraftLink('?draft=')).toEqual({ id: null, contentTypeId: null })
    expect(readDraftLink(`?draft=${draft}%0A`)).toEqual({ id: null, contentTypeId: null })
    expect(readDraftLink(`?draft=${draft}&draft=${draft}&type=incoming`)).toEqual({ id: null, contentTypeId: null })
    expect(readDraftLink(`?draft=${draft}&type=${type}&type=${type}`)).toEqual({ id: draft, contentTypeId: null })
  })
})
