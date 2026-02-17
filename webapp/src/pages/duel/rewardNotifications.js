export function buildRewardNotificationItems(payload, seenAchievementRewards) {
  if (!payload) return []

  const next = []
  const achievementUnlocks = Array.isArray(payload.achievement_unlocks) ? payload.achievement_unlocks : []
  const collectionDrops = Array.isArray(payload.collection_drops) ? payload.collection_drops : []

  achievementUnlocks.forEach((unlock, index) => {
    const achievement = unlock?.achievement
    if (!achievement?.title) return

    const dedupeKey = `achievement_${achievement.id || achievement.key || achievement.title}`
    if (seenAchievementRewards?.has(dedupeKey)) {
      return
    }

    seenAchievementRewards?.add(dedupeKey)
    next.push({
      id: `ach_${Date.now()}_${index}_${achievement.id || achievement.key || 'x'}`,
      type: 'achievement',
      icon: achievement.icon || '🏆',
      title: achievement.title,
      subtitle: achievement.description || '',
      rarity: achievement.rarity || 'common',
    })
  })

  collectionDrops.forEach((drop, index) => {
    const item = drop?.item
    if (!item?.name) return

    const isDuplicate = Boolean(drop?.is_duplicate)
    const coinBonus = isDuplicate
      ? Number(drop?.duplicate_compensation?.coins || 0)
      : Number(drop?.new_card_bonus?.coins || 0)
    const subtitle = isDuplicate
      ? `Дубликат обменян: +${coinBonus} монет`
      : `Редкость: ${item.rarity_label || drop.rarity_label || 'Обычная'}${coinBonus > 0 ? ` · +${coinBonus} монет` : ''}`

    next.push({
      id: `card_${Date.now()}_${index}_${item.id || item.key || 'x'}`,
      type: 'card',
      icon: isDuplicate ? '♻️' : '🃏',
      title: isDuplicate ? `Дубликат: ${item.name}` : `Карточка: ${item.name}`,
      subtitle,
      rarity: item.rarity || 'common',
    })
  })

  return next
}
