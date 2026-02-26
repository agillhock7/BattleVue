<template>
  <section class="panel preview-shell">
    <div class="preview-left">
      <div class="preview-head">
        <div>
          <p class="eyebrow">Live Build Preview</p>
          <h3>{{ botName }}</h3>
          <p class="muted">{{ archetypeLabel }} profile | Chassis: {{ chassisLabel }}</p>
        </div>
        <div class="lane-pill" :class="`lane-${laneClass}`">
          Lane: {{ laneLabel }}
        </div>
      </div>

      <div class="arena-stage">
        <div class="lane-grid">
          <div class="lane-col" :class="{ active: laneClass === 'left' }">
            <span>Left</span>
          </div>
          <div class="lane-col" :class="{ active: laneClass === 'mid' }">
            <span>Mid</span>
          </div>
          <div class="lane-col" :class="{ active: laneClass === 'right' }">
            <span>Right</span>
          </div>
        </div>

        <div class="bot-core-wrap" :class="`at-${laneClass}`">
          <div class="bot-orbit" v-for="(module, idx) in orbitModules" :key="`${module.slug}-${idx}`" :style="orbitStyle(idx, orbitModules.length)">
            <span :class="['module-dot', module.type]" :title="moduleLabel(module)"></span>
          </div>

          <div class="bot-core" :class="archetypeClass">
            <div class="bot-eye"></div>
            <div class="bot-eye"></div>
          </div>
        </div>
      </div>

      <div class="stats-grid">
        <div class="stat-row">
          <span>HP</span>
          <div class="bar"><i :style="{ width: `${hpPercent}%` }"></i></div>
          <strong>{{ hp }}</strong>
        </div>
        <div class="stat-row">
          <span>Speed</span>
          <div class="bar speed"><i :style="{ width: `${speedPercent}%` }"></i></div>
          <strong>{{ speed }}</strong>
        </div>
        <div class="stat-row">
          <span>Power</span>
          <div class="bar power"><i :style="{ width: `${powerPercent}%` }"></i></div>
          <strong>{{ power }}</strong>
        </div>
      </div>
    </div>

    <div class="preview-right">
      <div class="card col preview-card">
        <strong>Loadout Signature</strong>
        <div class="module-summary">
          <div>
            <span class="label">Weapon</span>
            <strong>{{ moduleCounts.weapon }}</strong>
          </div>
          <div>
            <span class="label">Defense</span>
            <strong>{{ moduleCounts.defense }}</strong>
          </div>
          <div>
            <span class="label">Mobility</span>
            <strong>{{ moduleCounts.mobility }}</strong>
          </div>
          <div>
            <span class="label">Utility</span>
            <strong>{{ moduleCounts.utility }}</strong>
          </div>
        </div>

        <div class="row chips" v-if="modules.length">
          <span v-for="(module, idx) in modules.slice(0, 8)" :key="`${module.slug}-${idx}`" class="module-chip" :class="module.type">
            {{ shortSlug(module.slug) }}
          </span>
        </div>
        <p class="muted" v-else>No modules mounted yet.</p>
      </div>

      <div class="card col preview-card">
        <strong>Behavior Snapshot</strong>
        <div class="action-bars">
          <div class="action-line">
            <span>Attack</span>
            <div class="bar"><i :style="{ width: `${actionPercents.attack}%` }"></i></div>
            <strong>{{ actionCounts.attack }}</strong>
          </div>
          <div class="action-line">
            <span>Guard</span>
            <div class="bar speed"><i :style="{ width: `${actionPercents.guard}%` }"></i></div>
            <strong>{{ actionCounts.guard }}</strong>
          </div>
          <div class="action-line">
            <span>Shift</span>
            <div class="bar power"><i :style="{ width: `${actionPercents.shift}%` }"></i></div>
            <strong>{{ actionCounts.shift }}</strong>
          </div>
          <div class="action-line">
            <span>Wait</span>
            <div class="bar neutral"><i :style="{ width: `${actionPercents.wait}%` }"></i></div>
            <strong>{{ actionCounts.wait }}</strong>
          </div>
        </div>

        <div class="muted" v-if="topRuleLabel">
          Top priority: {{ topRuleLabel }}
        </div>
        <div class="muted" v-else>No rules yet.</div>
      </div>

      <div class="card col preview-card">
        <strong>Readiness</strong>
        <div class="readiness-item">
          <span>Blueprint</span>
          <span :class="blueprintReady ? 'ok' : 'warn'">{{ blueprintReady ? 'Ready' : 'Needs Work' }}</span>
        </div>
        <div class="readiness-item">
          <span>Ruleset</span>
          <span :class="rulesetReady ? 'ok' : 'warn'">{{ rulesetReady ? 'Ready' : 'Needs Work' }}</span>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
  blueprint: any;
  ruleset: any;
  blueprintValidation: { valid: boolean; errors: string[] } | null;
  rulesetValidation: { valid: boolean; errors: string[] } | null;
}>();

const modules = computed(() => (Array.isArray(props.blueprint?.modules) ? props.blueprint.modules : []));
const orbitModules = computed(() => modules.value.slice(0, 6));

const hp = computed(() => Number(props.blueprint?.stats?.hp || 0));
const speed = computed(() => Number(props.blueprint?.stats?.speed || 0));
const power = computed(() => Number(props.blueprint?.stats?.power || 0));

const hpPercent = computed(() => clampPercent(hp.value));
const speedPercent = computed(() => clampPercent(speed.value));
const powerPercent = computed(() => clampPercent(power.value));

const laneClass = computed(() => {
  const lane = String(props.blueprint?.lane_pref || 'adaptive');
  if (lane === 'left' || lane === 'mid' || lane === 'right') {
    return lane;
  }
  return 'mid';
});

const laneLabel = computed(() => {
  const lane = String(props.blueprint?.lane_pref || 'adaptive');
  if (lane === 'adaptive') {
    return 'Adaptive';
  }
  if (lane === 'mid') {
    return 'Mid';
  }
  if (lane === 'left') {
    return 'Left';
  }
  return 'Right';
});

const chassisLabel = computed(() => {
  const slug = String(props.blueprint?.chassis || 'chassis-starter');
  return slug.replace(/-/g, ' ');
});

const botName = computed(() => {
  const name = String(props.blueprint?.name || '').trim();
  return name !== '' ? name : 'Unnamed Prototype';
});

const archetypeClass = computed(() => {
  if (hp.value >= 160 && speed.value <= 10) {
    return 'tank';
  }
  if (speed.value >= 16 && power.value >= 16) {
    return 'striker';
  }
  return 'balanced';
});

const archetypeLabel = computed(() => {
  if (archetypeClass.value === 'tank') {
    return 'Tank';
  }
  if (archetypeClass.value === 'striker') {
    return 'Striker';
  }
  return 'Balanced';
});

const moduleCounts = computed(() => {
  const counts = { weapon: 0, defense: 0, mobility: 0, utility: 0 };
  for (const module of modules.value) {
    const type = String(module?.type || 'weapon') as keyof typeof counts;
    if (counts[type] !== undefined) {
      counts[type] += 1;
    }
  }
  return counts;
});

const actionCounts = computed(() => {
  const out = { attack: 0, guard: 0, shift: 0, wait: 0 };
  const rules = Array.isArray(props.ruleset?.rules) ? props.ruleset.rules : [];
  for (const rule of rules) {
    const action = String(rule?.then?.action || 'wait');
    if (action === 'attack_lane') {
      out.attack += 1;
    } else if (action === 'guard') {
      out.guard += 1;
    } else if (action === 'shift_lane') {
      out.shift += 1;
    } else {
      out.wait += 1;
    }
  }
  return out;
});

const actionPercents = computed(() => {
  const total = Math.max(
    1,
    actionCounts.value.attack + actionCounts.value.guard + actionCounts.value.shift + actionCounts.value.wait
  );
  return {
    attack: Math.round((actionCounts.value.attack / total) * 100),
    guard: Math.round((actionCounts.value.guard / total) * 100),
    shift: Math.round((actionCounts.value.shift / total) * 100),
    wait: Math.round((actionCounts.value.wait / total) * 100),
  };
});

const topRuleLabel = computed(() => {
  const rules = Array.isArray(props.ruleset?.rules) ? [...props.ruleset.rules] : [];
  if (!rules.length) {
    return '';
  }
  rules.sort((a, b) => Number(b?.priority || 0) - Number(a?.priority || 0));
  const top = rules[0];
  const sensor = String(top?.when?.sensor || 'sensor');
  const action = String(top?.then?.action || 'wait').replace('_', ' ');
  return `${sensor} -> ${action}`;
});

const blueprintReady = computed(() => {
  if (props.blueprintValidation) {
    return props.blueprintValidation.valid;
  }
  return botName.value !== 'Unnamed Prototype' && modules.value.length > 0;
});

const rulesetReady = computed(() => {
  if (props.rulesetValidation) {
    return props.rulesetValidation.valid;
  }
  return Array.isArray(props.ruleset?.rules) && props.ruleset.rules.length > 0;
});

function clampPercent(value: number) {
  const clamped = Math.max(0, Math.min(200, value));
  return Math.round((clamped / 200) * 100);
}

function orbitStyle(index: number, total: number) {
  const safeTotal = Math.max(1, total);
  const angle = (360 / safeTotal) * index;
  return {
    transform: `rotate(${angle}deg) translate(56px) rotate(-${angle}deg)`,
  };
}

function shortSlug(slug: string) {
  return slug.replace(/^module-/, '').replace(/-/g, ' ');
}

function moduleLabel(module: any) {
  return `${module.type}: ${module.slug}`;
}
</script>

<style scoped>
.preview-shell {
  display: grid;
  grid-template-columns: 1.2fr 0.8fr;
  gap: 12px;
  background:
    radial-gradient(circle at 15% 12%, rgba(56, 189, 248, 0.16), transparent 42%),
    radial-gradient(circle at 85% 86%, rgba(34, 197, 94, 0.14), transparent 42%),
    rgba(10, 24, 45, 0.78);
}

.preview-left,
.preview-right {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.preview-head {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: flex-start;
}

.preview-head h3 {
  margin: 2px 0 0;
}

.eyebrow {
  margin: 0;
  font-size: 11px;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: #9fb1cc;
}

.lane-pill {
  border: 1px solid rgba(142, 166, 203, 0.35);
  border-radius: 999px;
  padding: 6px 10px;
  font-size: 12px;
  background: rgba(7, 15, 31, 0.5);
}

.lane-pill.lane-left,
.lane-pill.lane-mid,
.lane-pill.lane-right {
  border-color: rgba(56, 189, 248, 0.45);
}

.arena-stage {
  border: 1px solid rgba(142, 166, 203, 0.24);
  border-radius: 12px;
  background: rgba(5, 15, 29, 0.78);
  padding: 14px;
  min-height: 230px;
  position: relative;
  overflow: hidden;
}

.lane-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  height: 100%;
}

.lane-col {
  border: 1px dashed rgba(142, 166, 203, 0.22);
  border-radius: 10px;
  position: relative;
  min-height: 190px;
}

.lane-col span {
  position: absolute;
  top: 8px;
  left: 8px;
  font-size: 12px;
  color: #8fa6c8;
}

.lane-col.active {
  border-color: rgba(56, 189, 248, 0.56);
  background: linear-gradient(180deg, rgba(56, 189, 248, 0.15), rgba(56, 189, 248, 0.03));
}

.bot-core-wrap {
  position: absolute;
  bottom: 34px;
  left: 50%;
  width: 132px;
  height: 132px;
  margin-left: -66px;
  transition: transform 0.35s ease;
}

.bot-core-wrap.at-left {
  transform: translateX(-100px);
}

.bot-core-wrap.at-mid {
  transform: translateX(0);
}

.bot-core-wrap.at-right {
  transform: translateX(100px);
}

.bot-orbit {
  position: absolute;
  top: 50%;
  left: 50%;
  margin: -6px 0 0 -6px;
}

.module-dot {
  width: 12px;
  height: 12px;
  border-radius: 999px;
  display: block;
  box-shadow: 0 0 10px rgba(14, 165, 233, 0.6);
}

.module-dot.weapon {
  background: #f97316;
}

.module-dot.defense {
  background: #22c55e;
}

.module-dot.mobility {
  background: #38bdf8;
}

.module-dot.utility {
  background: #c084fc;
}

.bot-core {
  width: 72px;
  height: 72px;
  margin: 28px auto 0;
  border-radius: 20px;
  border: 1px solid rgba(142, 166, 203, 0.32);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  animation: float 2.4s ease-in-out infinite;
  box-shadow: inset 0 0 18px rgba(56, 189, 248, 0.18);
}

.bot-core.balanced {
  background: linear-gradient(180deg, rgba(56, 189, 248, 0.28), rgba(8, 47, 73, 0.56));
}

.bot-core.tank {
  background: linear-gradient(180deg, rgba(34, 197, 94, 0.28), rgba(21, 75, 52, 0.56));
}

.bot-core.striker {
  background: linear-gradient(180deg, rgba(249, 115, 22, 0.28), rgba(100, 43, 10, 0.56));
}

.bot-eye {
  width: 10px;
  height: 10px;
  border-radius: 999px;
  background: #dbeafe;
  box-shadow: 0 0 8px rgba(219, 234, 254, 0.8);
}

.stats-grid,
.action-bars {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.stat-row,
.action-line {
  display: grid;
  grid-template-columns: 58px minmax(0, 1fr) 38px;
  gap: 8px;
  align-items: center;
  font-size: 13px;
}

.bar {
  height: 8px;
  border-radius: 999px;
  background: rgba(12, 24, 44, 0.84);
  border: 1px solid rgba(142, 166, 203, 0.24);
  overflow: hidden;
}

.bar i {
  display: block;
  height: 100%;
  background: linear-gradient(90deg, #22d3ee, #3b82f6);
}

.bar.speed i {
  background: linear-gradient(90deg, #34d399, #10b981);
}

.bar.power i {
  background: linear-gradient(90deg, #fb923c, #f97316);
}

.bar.neutral i {
  background: linear-gradient(90deg, #94a3b8, #64748b);
}

.preview-card {
  background: rgba(7, 18, 35, 0.72);
}

.module-summary {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}

.module-summary > div {
  border: 1px solid rgba(142, 166, 203, 0.2);
  border-radius: 10px;
  padding: 8px;
  display: flex;
  flex-direction: column;
}

.module-summary .label {
  font-size: 11px;
  color: #8fa6c8;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.chips {
  gap: 6px;
}

.module-chip {
  border: 1px solid rgba(142, 166, 203, 0.3);
  border-radius: 999px;
  padding: 4px 8px;
  font-size: 11px;
  text-transform: capitalize;
}

.module-chip.weapon {
  border-color: rgba(249, 115, 22, 0.45);
}

.module-chip.defense {
  border-color: rgba(34, 197, 94, 0.45);
}

.module-chip.mobility {
  border-color: rgba(56, 189, 248, 0.45);
}

.module-chip.utility {
  border-color: rgba(192, 132, 252, 0.45);
}

.readiness-item {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
}

.ok {
  color: #4ade80;
}

.warn {
  color: #f59e0b;
}

@keyframes float {
  0% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-6px);
  }
  100% {
    transform: translateY(0);
  }
}

@media (max-width: 960px) {
  .preview-shell {
    grid-template-columns: 1fr;
  }

  .bot-core-wrap.at-left,
  .bot-core-wrap.at-right {
    transform: translateX(0);
  }
}
</style>
