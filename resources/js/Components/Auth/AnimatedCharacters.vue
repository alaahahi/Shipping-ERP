<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import EyeBall from './EyeBall.vue'
import Pupil from './Pupil.vue'

const props = defineProps({
  isTyping: { type: Boolean, default: false },
  showPassword: { type: Boolean, default: false },
  passwordLength: { type: Number, default: 0 },
})

const mouseX = ref(0)
const mouseY = ref(0)
const isPurpleBlinking = ref(false)
const isBlackBlinking = ref(false)
const isLookingAtEachOther = ref(false)
const isPurplePeeking = ref(false)

const activeTimers = new Set()

const purpleRef = ref(null)
const blackRef = ref(null)
const yellowRef = ref(null)
const orangeRef = ref(null)

const onMouseMove = (e) => {
  mouseX.value = e.clientX
  mouseY.value = e.clientY
}

onMounted(() => {
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        window.addEventListener('mousemove', onMouseMove);
    }

    startPurpleBlink();
    startBlackBlink();
});

onUnmounted(() => {
  window.removeEventListener('mousemove', onMouseMove)
  clearAllTimers()
})

let typingTimer = 0
let peekTimer = 0

const randomBlink = () => Math.random() * 4000 + 3000

const trackTimeout = (callback, delay) => {
  const id = window.setTimeout(() => {
    activeTimers.delete(id)
    callback()
  }, delay)

  activeTimers.add(id)
  return id
}

const clearTrackedTimeout = (id) => {
  clearTimeout(id)
  activeTimers.delete(id)
}

const clearAllTimers = () => {
  activeTimers.forEach((id) => clearTimeout(id))
  activeTimers.clear()
}

const startPurpleBlink = () => {
  trackTimeout(() => {
    isPurpleBlinking.value = true
    trackTimeout(() => {
      isPurpleBlinking.value = false
      startPurpleBlink()
    }, 150)
  }, randomBlink())
}

const startBlackBlink = () => {
  trackTimeout(() => {
    isBlackBlinking.value = true
    trackTimeout(() => {
      isBlackBlinking.value = false
      startBlackBlink()
    }, 150)
  }, randomBlink())
}

watch(
  () => props.isTyping,
  (typing) => {
    clearTrackedTimeout(typingTimer)
    if (typing) {
      isLookingAtEachOther.value = true
      typingTimer = trackTimeout(() => {
        isLookingAtEachOther.value = false
      }, 800)
    } else {
      isLookingAtEachOther.value = false
    }
  },
  { immediate: true },
)

watch(
  () => [props.passwordLength, props.showPassword],
  ([len, show]) => {
    clearTrackedTimeout(peekTimer)

    if (len > 0 && show) {
      const schedulePeek = () => {
        peekTimer = trackTimeout(() => {
          isPurplePeeking.value = true
          trackTimeout(() => {
            isPurplePeeking.value = false
            schedulePeek()
          }, 800)
        }, Math.random() * 3000 + 2000)
      }
      schedulePeek()
    } else {
      isPurplePeeking.value = false
    }
  },
  { immediate: true },
)

const calculatePosition = (elRef) => {
  if (!elRef.value) return { faceX: 0, faceY: 0, bodySkew: 0 }
  const rect = elRef.value.getBoundingClientRect()
  const centerX = rect.left + rect.width / 2
  const centerY = rect.top + rect.height / 3
  const deltaX = mouseX.value - centerX
  const deltaY = mouseY.value - centerY

  return {
    faceX: Math.max(-15, Math.min(15, deltaX / 20)),
    faceY: Math.max(-10, Math.min(10, deltaY / 30)),
    bodySkew: Math.max(-6, Math.min(6, -deltaX / 120)),
  }
}

const purplePos = computed(() => calculatePosition(purpleRef))
const blackPos = computed(() => calculatePosition(blackRef))
const yellowPos = computed(() => calculatePosition(yellowRef))
const orangePos = computed(() => calculatePosition(orangeRef))

const isHidingPassword = computed(() => props.passwordLength > 0 && !props.showPassword)
</script>

<template>
  <div class="relative" style="width: 550px; height: 400px">
    <div
      ref="purpleRef"
      class="absolute bottom-0 transition-all duration-700 ease-in-out"
      :style="{
        left: '70px',
        width: '180px',
        height: isTyping || isHidingPassword ? '440px' : '400px',
        backgroundColor: '#6C3FF5',
        borderRadius: '10px 10px 0 0',
        zIndex: 1,
        transform: passwordLength > 0 && showPassword
          ? 'skewX(0deg)'
          : isTyping || isHidingPassword
            ? `skewX(${purplePos.bodySkew - 12}deg) translateX(40px)`
            : `skewX(${purplePos.bodySkew}deg)`,
        transformOrigin: 'bottom center',
      }"
    >
      <div
        class="absolute flex gap-8 transition-all duration-700 ease-in-out"
        :style="{
          left: passwordLength > 0 && showPassword ? '20px' : isLookingAtEachOther ? '55px' : `${45 + purplePos.faceX}px`,
          top: passwordLength > 0 && showPassword ? '35px' : isLookingAtEachOther ? '65px' : `${40 + purplePos.faceY}px`,
        }"
      >
        <EyeBall
          :size="18"
          :pupil-size="7"
          :max-distance="5"
          eye-color="white"
          pupil-color="#2D2D2D"
          :is-blinking="isPurpleBlinking"
          :force-look-x="passwordLength > 0 && showPassword ? (isPurplePeeking ? 4 : -4) : isLookingAtEachOther ? 3 : undefined"
          :force-look-y="passwordLength > 0 && showPassword ? (isPurplePeeking ? 5 : -4) : isLookingAtEachOther ? 4 : undefined"
        />
        <EyeBall
          :size="18"
          :pupil-size="7"
          :max-distance="5"
          eye-color="white"
          pupil-color="#2D2D2D"
          :is-blinking="isPurpleBlinking"
          :force-look-x="passwordLength > 0 && showPassword ? (isPurplePeeking ? 4 : -4) : isLookingAtEachOther ? 3 : undefined"
          :force-look-y="passwordLength > 0 && showPassword ? (isPurplePeeking ? 5 : -4) : isLookingAtEachOther ? 4 : undefined"
        />
      </div>
    </div>

    <div
      ref="blackRef"
      class="absolute bottom-0 transition-all duration-700 ease-in-out"
      :style="{
        left: '240px',
        width: '120px',
        height: '310px',
        backgroundColor: '#2D2D2D',
        borderRadius: '8px 8px 0 0',
        zIndex: 2,
        transform: passwordLength > 0 && showPassword
          ? 'skewX(0deg)'
          : isLookingAtEachOther
            ? `skewX(${blackPos.bodySkew * 1.5 + 10}deg) translateX(20px)`
            : isTyping || isHidingPassword
              ? `skewX(${blackPos.bodySkew * 1.5}deg)`
              : `skewX(${blackPos.bodySkew}deg)`,
        transformOrigin: 'bottom center',
      }"
    >
      <div
        class="absolute flex gap-6 transition-all duration-700 ease-in-out"
        :style="{
          left: passwordLength > 0 && showPassword ? '10px' : isLookingAtEachOther ? '32px' : `${26 + blackPos.faceX}px`,
          top: passwordLength > 0 && showPassword ? '28px' : isLookingAtEachOther ? '12px' : `${32 + blackPos.faceY}px`,
        }"
      >
        <EyeBall
          :size="16"
          :pupil-size="6"
          :max-distance="4"
          eye-color="white"
          pupil-color="#2D2D2D"
          :is-blinking="isBlackBlinking"
          :force-look-x="passwordLength > 0 && showPassword ? -4 : isLookingAtEachOther ? 0 : undefined"
          :force-look-y="passwordLength > 0 && showPassword ? -4 : isLookingAtEachOther ? -4 : undefined"
        />
        <EyeBall
          :size="16"
          :pupil-size="6"
          :max-distance="4"
          eye-color="white"
          pupil-color="#2D2D2D"
          :is-blinking="isBlackBlinking"
          :force-look-x="passwordLength > 0 && showPassword ? -4 : isLookingAtEachOther ? 0 : undefined"
          :force-look-y="passwordLength > 0 && showPassword ? -4 : isLookingAtEachOther ? -4 : undefined"
        />
      </div>
    </div>

    <div
      ref="orangeRef"
      class="absolute bottom-0 transition-all duration-700 ease-in-out"
      :style="{
        left: '0px',
        width: '240px',
        height: '200px',
        zIndex: 3,
        backgroundColor: '#FF9B6B',
        borderRadius: '120px 120px 0 0',
        transform: passwordLength > 0 && showPassword ? 'skewX(0deg)' : `skewX(${orangePos.bodySkew}deg)`,
        transformOrigin: 'bottom center',
      }"
    >
      <div
        class="absolute flex gap-8 transition-all duration-200 ease-out"
        :style="{
          left: passwordLength > 0 && showPassword ? '50px' : `${82 + orangePos.faceX}px`,
          top: passwordLength > 0 && showPassword ? '85px' : `${90 + orangePos.faceY}px`,
        }"
      >
        <Pupil :size="12" :max-distance="5" pupil-color="#2D2D2D" :force-look-x="passwordLength > 0 && showPassword ? -5 : undefined" :force-look-y="passwordLength > 0 && showPassword ? -4 : undefined" />
        <Pupil :size="12" :max-distance="5" pupil-color="#2D2D2D" :force-look-x="passwordLength > 0 && showPassword ? -5 : undefined" :force-look-y="passwordLength > 0 && showPassword ? -4 : undefined" />
      </div>
    </div>

    <div
      ref="yellowRef"
      class="absolute bottom-0 transition-all duration-700 ease-in-out"
      :style="{
        left: '310px',
        width: '140px',
        height: '230px',
        backgroundColor: '#E8D754',
        borderRadius: '70px 70px 0 0',
        zIndex: 4,
        transform: passwordLength > 0 && showPassword ? 'skewX(0deg)' : `skewX(${yellowPos.bodySkew}deg)`,
        transformOrigin: 'bottom center',
      }"
    >
      <div
        class="absolute flex gap-6 transition-all duration-200 ease-out"
        :style="{
          left: passwordLength > 0 && showPassword ? '20px' : `${52 + yellowPos.faceX}px`,
          top: passwordLength > 0 && showPassword ? '35px' : `${40 + yellowPos.faceY}px`,
        }"
      >
        <Pupil :size="12" :max-distance="5" pupil-color="#2D2D2D" :force-look-x="passwordLength > 0 && showPassword ? -5 : undefined" :force-look-y="passwordLength > 0 && showPassword ? -4 : undefined" />
        <Pupil :size="12" :max-distance="5" pupil-color="#2D2D2D" :force-look-x="passwordLength > 0 && showPassword ? -5 : undefined" :force-look-y="passwordLength > 0 && showPassword ? -4 : undefined" />
      </div>
      <div
        class="absolute w-20 h-[4px] bg-[#2D2D2D] rounded-full transition-all duration-200 ease-out"
        :style="{
          left: passwordLength > 0 && showPassword ? '10px' : `${40 + yellowPos.faceX}px`,
          top: passwordLength > 0 && showPassword ? '88px' : `${88 + yellowPos.faceY}px`,
        }"
      />
    </div>
  </div>
</template>
