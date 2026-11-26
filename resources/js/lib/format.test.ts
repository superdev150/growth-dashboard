import { describe, expect, it } from 'vitest'
import { change, count, money, ratio, shortDate } from './format'

describe('money', () => {
  it('keeps cents under a thousand and drops them above', () => {
    expect(money(98.7, 'USD')).toBe('$98.70')
    expect(money(7426.4, 'USD')).toBe('$7,426')
  })
  it('compacts large values only when asked', () => {
    expect(money(12840, 'USD', true)).toBe('$12.8K')
    expect(money(12840, 'USD')).toBe('$12,840')
    expect(money(150, 'USD', true)).toBe('$150.00')
  })
  it('uses the report currency', () => {
    expect(money(10, 'EUR')).toBe('€10.00')
  })
})

describe('change', () => {
  it('signs with a real minus and one decimal', () => {
    expect(change(0.0421)).toBe('+4.2%')
    expect(change(-0.031)).toBe('−3.1%')
    expect(change(0.0004)).toBe('0%')
    expect(change(1.5)).toBe('+150%')
  })
  it('shows a dash when there is nothing to compare with', () => {
    expect(change(null)).toBe('—')
  })
})

describe('other formats', () => {
  it('counts and ratios', () => {
    expect(count(4039)).toBe('4,039')
    expect(count(123456, true)).toBe('123.5K')
    expect(ratio(1.6)).toBe('1.60×')
  })
  it('reads API dates as UTC days, whatever the local zone', () => {
    expect(shortDate('2026-03-01')).toBe('Mar 1')
  })
})
