<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  size: { type: Number, default: 12 },
  maxDistance: { type: Number, default: 5 },
  pupilColor: { type: String, default: 'black' },
  forceLookX: { type: Number, default: undefined },
  forceLookY: { type: Number, default: undefined },
})

const mouseX = ref(0)
const mouseY = ref(0)
const pupilRef = ref(null)

const onMouseMove = (e) => {
  mouseX.value = e.clientX
  mouseY.value = e.clientY
}

onMounted(() => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    window.addEventListener('mousemove', onMouseMove);
});
onUnmounted(() => window.removeEventListener('mousemove', onMouseMove))

const pupilPosition = computed(() => {
  if (props.forceLookX !== undefined && props.forceLookY !== undefined) {
    return { x: props.forceLookX, y: props.forceLookY }
  }
  if (!pupilRef.value) return { x: 0, y: 0 }

  const rect = pupilRef.value.getBoundingClientRect()
  const centerX = rect.left + rect.width / 2
  const centerY = rect.top + rect.height / 2
  const deltaX = mouseX.value - centerX
  const deltaY = mouseY.value - centerY
  const distance = Math.min(Math.sqrt(deltaX ** 2 + deltaY ** 2), props.maxDistance)
  const angle = Math.atan2(deltaY, deltaX)
  return {
    x: Math.cos(angle) * distance,
    y: Math.sin(angle) * distance,
  }
})
</script>

<template>
  <div
    ref="pupilRef"
    class="rounded-full"
    :style="{
      width: `${size}px`,
      height: `${size}px`,
      backgroundColor: pupilColor,
      transform: `translate(${pupilPosition.x}px, ${pupilPosition.y}px)`,
      transition: 'transform 0.1s ease-out',
    }"
  />
</template>
