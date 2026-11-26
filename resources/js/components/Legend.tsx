interface Item {
  id: string
  name: string
  color: string
}

/** Identity never rests on color alone: every series is named next to its swatch. */
export function Legend({ items }: { items: Item[] }) {
  if (items.length < 2) return null
  return (
    <ul className="legend">
      {items.map((item) => (
        <li key={item.id}>
          <span className="swatch" style={{ background: item.color }} aria-hidden="true" />
          {item.name}
        </li>
      ))}
    </ul>
  )
}
