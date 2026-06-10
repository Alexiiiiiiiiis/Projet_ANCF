import { describe, it, expect } from 'vitest'
import {
  TRANSPORT_COLORS,
  TRANSPORT_LABELS,
  SEVERITY_COLORS,
  SEVERITY_LABELS,
  type TransportType,
  type AlertSeverity,
} from '../types/transport'

const ALL_TRANSPORT_TYPES: TransportType[] = ['METRO', 'RER', 'TRAM', 'BUS']
const ALL_SEVERITY_TYPES: AlertSeverity[] = ['INFO', 'MODERATE', 'MAJOR']

describe('TRANSPORT_COLORS', () => {
  it('defines a hex color for each transport type', () => {
    for (const type of ALL_TRANSPORT_TYPES) {
      expect(TRANSPORT_COLORS[type]).toBeDefined()
      expect(TRANSPORT_COLORS[type]).toMatch(/^#[0-9a-fA-F]{6}$/)
    }
  })

  it('metro is blue', () => {
    expect(TRANSPORT_COLORS['METRO']).toBe('#0052CC')
  })

  it('bus is orange', () => {
    expect(TRANSPORT_COLORS['BUS']).toBe('#FF6900')
  })
})

describe('TRANSPORT_LABELS', () => {
  it('defines a label for each transport type', () => {
    for (const type of ALL_TRANSPORT_TYPES) {
      expect(TRANSPORT_LABELS[type]).toBeDefined()
      expect(typeof TRANSPORT_LABELS[type]).toBe('string')
    }
  })

  it('RER label is "RER"', () => {
    expect(TRANSPORT_LABELS['RER']).toBe('RER')
  })
})

describe('SEVERITY_COLORS', () => {
  it('defines a color for each severity', () => {
    for (const severity of ALL_SEVERITY_TYPES) {
      expect(SEVERITY_COLORS[severity]).toBeDefined()
      expect(SEVERITY_COLORS[severity]).toMatch(/^#[0-9a-fA-F]{6}$/)
    }
  })
})

describe('SEVERITY_LABELS', () => {
  it('defines a label for each severity', () => {
    for (const severity of ALL_SEVERITY_TYPES) {
      expect(SEVERITY_LABELS[severity]).toBeDefined()
    }
  })

  it('MAJOR label is "MAJEUR"', () => {
    expect(SEVERITY_LABELS['MAJOR']).toBe('MAJEUR')
  })
})

describe('Password validation rules', () => {
  const isValidPassword = (pwd: string) => pwd.length >= 8

  it('rejects passwords shorter than 8 chars', () => {
    expect(isValidPassword('abc')).toBe(false)
    expect(isValidPassword('1234567')).toBe(false)
  })

  it('accepts passwords of 8+ chars', () => {
    expect(isValidPassword('Admin1234!')).toBe(true)
    expect(isValidPassword('abcdefgh')).toBe(true)
  })
})

describe('Email validation', () => {
  const isValidEmail = (email: string) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)

  it('validates correct emails', () => {
    expect(isValidEmail('user@ancf.fr')).toBe(true)
    expect(isValidEmail('admin@ancf.fr')).toBe(true)
  })

  it('rejects invalid emails', () => {
    expect(isValidEmail('notanemail')).toBe(false)
    expect(isValidEmail('@missing.com')).toBe(false)
    expect(isValidEmail('missing@')).toBe(false)
  })
})
