<template>
  <section class="panel preview-shell">
    <div class="scene-col">
      <div class="scene-head">
        <div>
          <p class="eyebrow">Build Bay</p>
          <h3>{{ botName }}</h3>
          <p class="muted">{{ archetypeLabel }} rig | Chassis {{ chassisLabel }}</p>
        </div>
        <div class="score-gauge" :style="{ '--score': `${combatScorePercent}%` }">
          <span>{{ combatScore }}</span>
          <small>Combat</small>
        </div>
      </div>

      <div class="hangar">
        <div class="hangar-glow"></div>
        <div class="hangar-grid"></div>
        <div class="scanline"></div>

        <div class="lane-deck">
          <div class="lane" :class="{ active: laneClass === 'left' }"><span>LEFT</span></div>
          <div class="lane" :class="{ active: laneClass === 'mid' }"><span>MID</span></div>
          <div class="lane" :class="{ active: laneClass === 'right' }"><span>RIGHT</span></div>
        </div>

        <div class="target-track" :class="`at-${laneClass}`">
          <span></span>
        </div>

        <div class="bot-stage" :class="`lane-${laneClass}`">
          <div class="bot-shadow"></div>

          <div class="module-orbit ring-a">
            <i
              v-for="(module, idx) in ringAModules"
              :key="`a-${module.slug}-${idx}`"
              :class="['satellite', module.type]"
              :style="orbitStyle(idx, ringAModules.length, 62)"
              :title="moduleLabel(module)"
            ></i>
          </div>

          <div class="module-orbit ring-b">
            <i
              v-for="(module, idx) in ringBModules"
              :key="`b-${module.slug}-${idx}`"
              :class="['satellite', module.type]"
              :style="orbitStyle(idx, ringBModules.length, 86)"
              :title="moduleLabel(module)"
            ></i>
          </div>

          <div class="bot-frame" :class="archetypeClass">
            <div class="bot-antenna"></div>

            <div class="bot-head">
              <div class="visor">
                <span></span>
              </div>
            </div>

            <div class="bot-arm left"></div>
            <div class="bot-arm right"></div>

            <div class="bot-torso">
              <div class="core-ring"></div>
              <div class="core-dot"></div>
            </div>

            <div class="bot-leg left"></div>
            <div class="bot-leg right"></div>

            <div class="thrusters">
              <span></span>
              <span></span>
            </div>
          </div>
        </div>
      </div>

      <div class="stats-panel">
        <div class="stat-row">
          <span>HP</span>
          <div class="bar hp"><i :style="{ width: `${hpPercent}%` }"></i></div>
          <strong>{{ hp }}</strong>
        </div>
        <div class="stat-row">
          <span>SPD</span>
          <div class="bar speed"><i :style="{ width: `${speedPercent}%` }"></i></div>
          <strong>{{ speed }}</strong>
        </div>
        <div class="stat-row">
          <span>PWR</span>
          <div class="bar power"><i :style="{ width: `${powerPercent}%` }"></i></div>
          <strong>{{ power }}</strong>
        </div>
      </div>
    </div>

    <div class="intel-col">
      <div class="card col intel-card">
        <strong>Loadout Matrix</strong>
        <div class="module-matrix">
          <div>
            <label>Weapon</label>
            <strong>{{ moduleCounts.weapon }}</strong>
          </div>
          <div>
            <label>Defense</label>
            <strong>{{ moduleCounts.defense }}</strong>
          </div>
          <div>
            <label>Mobility</label>
            <strong>{{ moduleCounts.mobility }}</strong>
          </div>
          <div>
            <label>Utility</label>
            <strong>{{ moduleCounts.utility }}</strong>
          </div>
        </div>

        <div class="chip-cloud" v-if="modules.length">
          <span
            v-for="(module, idx) in modules.slice(0, 10)"
            :key="`${module.slug}-${idx}`"
            class="module-chip"
            :class="module.type"
          >
            {{ shortSlug(module.slug) }}
          </span>
        </div>
        <p class="muted" v-else>No modules mounted yet.</p>
      </div>

      <div class="card col intel-card">
        <strong>Behavior Signature</strong>
        <div class="action-row">
          <span>Attack</span>
          <div class="bar attack"><i :style="{ width: `${actionPercents.attack}%` }"></i></div>
          <strong>{{ actionCounts.attack }}</strong>
        </div>
        <div class="action-row">
          <span>Guard</span>
          <div class="bar guard"><i :style="{ width: `${actionPercents.guard}%` }"></i></div>
          <strong>{{ actionCounts.guard }}</strong>
        </div>
        <div class="action-row">
          <span>Shift</span>
          <div class="bar shift"><i :style="{ width: `${actionPercents.shift}%` }"></i></div>
          <strong>{{ actionCounts.shift }}</strong>
        </div>
        <div class="action-row">
          <span>Wait</span>
          <div class="bar wait"><i :style="{ width: `${actionPercents.wait}%` }"></i></div>
          <strong>{{ actionCounts.wait }}</strong>
        </div>

        <div class="muted" v-if="topRuleLabel">Top rule: {{ topRuleLabel }}</div>
        <div class="muted" v-else>No rules yet.</div>
      </div>

      <div class="card col intel-card">
        <strong>Deployment Readiness</strong>
        <div class="readiness-line">
          <span>Blueprint</span>
          <b :class="blueprintReady ? 'ok' : 'warn'">{{ blueprintReady ? 'Ready' : 'Needs Work' }}</b>
        </div>
        <div class="readiness-line">
          <span>Ruleset</span>
          <b :class="rulesetReady ? 'ok' : 'warn'">{{ rulesetReady ? 'Ready' : 'Needs Work' }}</b>
        </div>
        <div class="readiness-line">
          <span>Lane Plan</span>
          <b>{{ laneLabel }}</b>
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

const ringAModules = computed(() => modules.value.slice(0, 4));
const ringBModules = computed(() => modules.value.slice(4, 8));

const hp = computed(() => Number(props.blueprint?.stats?.hp || 0));
const speed = computed(() => Number(props.blueprint?.stats?.speed || 0));
const power = computed(() => Number(props.blueprint?.stats?.power || 0));

const hpPercent = computed(() => clampPercent(hp.value, 220));
const speedPercent = computed(() => clampPercent(speed.value, 220));
const powerPercent = computed(() => clampPercent(power.value, 220));

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
  if (lane === 'left') {
    return 'Left';
  }
  if (lane === 'right') {
    return 'Right';
  }
  return 'Mid';
});

const botName = computed(() => {
  const name = String(props.blueprint?.name || '').trim();
  return name !== '' ? name : 'Unnamed Prototype';
});

const chassisLabel = computed(() => String(props.blueprint?.chassis || 'chassis-starter').replace(/-/g, ' '));

const archetypeClass = computed(() => {
  if (hp.value >= 170 && speed.value <= 10) {
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
  const total = Math.max(1, actionCounts.value.attack + actionCounts.value.guard + actionCounts.value.shift + actionCounts.value.wait);
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
  return `${String(top?.when?.sensor || 'sensor')} => ${String(top?.then?.action || 'wait')}`;
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

const combatScore = computed(() => {
  const moduleWeight = modules.value.length * 8;
  const statWeight = hp.value * 0.35 + speed.value * 0.3 + power.value * 0.35;
  return Math.max(1, Math.round(Math.min(100, statWeight / 2.2 + moduleWeight)));
});

const combatScorePercent = computed(() => combatScore.value);

function clampPercent(value: number, max: number) {
  const safe = Math.max(0, Math.min(max, value));
  return Math.round((safe / max) * 100);
}

function orbitStyle(index: number, total: number, radius: number) {
  const safeTotal = Math.max(1, total);
  const angle = (360 / safeTotal) * index;
  return {
    transform: `rotate(${angle}deg) translate(${radius}px) rotate(-${angle}deg)`,
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
  grid-template-columns: 1.35fr 0.85fr;
  gap: 14px;
  background:
    radial-gradient(circle at 12% 10%, rgba(14, 165, 233, 0.16), transparent 45%),
    radial-gradient(circle at 88% 90%, rgba(34, 197, 94, 0.12), transparent 40%),
    rgba(10, 24, 45, 0.8);
}

.scene-col,
.intel-col {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.scene-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}

.scene-head h3 {
  margin: 2px 0 0;
}

.eyebrow {
  margin: 0;
  font-size: 11px;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: #8ca4c8;
}

.score-gauge {
  --score: 0%;
  width: 76px;
  height: 76px;
  border-radius: 999px;
  background: conic-gradient(#22d3ee var(--score), rgba(148, 163, 184, 0.22) 0);
  display: grid;
  place-items: center;
  position: relative;
}

.score-gauge::after {
  content: '';
  position: absolute;
  inset: 8px;
  border-radius: 999px;
  background: rgba(6, 17, 33, 0.94);
  border: 1px solid rgba(142, 166, 203, 0.25);
}

.score-gauge span,
.score-gauge small {
  position: relative;
  z-index: 1;
  text-align: center;
  line-height: 1;
}

.score-gauge span {
  font-weight: 700;
  font-size: 15px;
}

.score-gauge small {
  display: block;
  margin-top: 2px;
  font-size: 10px;
  color: #9fb1cc;
}

.hangar {
  position: relative;
  border: 1px solid rgba(142, 166, 203, 0.25);
  border-radius: 14px;
  overflow: hidden;
  min-height: 340px;
  background: rgba(4, 14, 28, 0.9);
}

.hangar-glow {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(circle at 50% 72%, rgba(56, 189, 248, 0.28), transparent 50%),
    radial-gradient(circle at 20% 30%, rgba(59, 130, 246, 0.2), transparent 35%),
    radial-gradient(circle at 80% 30%, rgba(34, 197, 94, 0.13), transparent 35%);
  pointer-events: none;
}

.hangar-grid {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(148, 163, 184, 0.08) 1px, transparent 1px),
    linear-gradient(90deg, rgba(148, 163, 184, 0.08) 1px, transparent 1px);
  background-size: 26px 26px;
  mask-image: linear-gradient(to bottom, rgba(255, 255, 255, 0.35), rgba(255, 255, 255, 0));
}

.scanline {
  position: absolute;
  inset: 0;
  background: linear-gradient(to bottom, transparent 0%, rgba(34, 211, 238, 0.2) 48%, transparent 100%);
  transform: translateY(-100%);
  animation: scan 4.2s linear infinite;
}

.lane-deck {
  position: absolute;
  inset: 12px 12px auto;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}

.lane {
  border: 1px dashed rgba(142, 166, 203, 0.28);
  border-radius: 10px;
  min-height: 220px;
  position: relative;
}

.lane span {
  position: absolute;
  top: 8px;
  left: 8px;
  font-size: 11px;
  letter-spacing: 0.08em;
  color: #8ca4c8;
}

.lane.active {
  border-color: rgba(34, 211, 238, 0.55);
  box-shadow: inset 0 0 40px rgba(14, 165, 233, 0.2);
}

.target-track {
  position: absolute;
  bottom: 46px;
  left: 50%;
  transform: translateX(-50%);
  width: 240px;
  height: 2px;
  background: linear-gradient(90deg, transparent, rgba(34, 211, 238, 0.65), transparent);
}

.target-track span {
  position: absolute;
  top: -3px;
  left: 0;
  width: 8px;
  height: 8px;
  border-radius: 999px;
  background: #67e8f9;
  box-shadow: 0 0 12px rgba(103, 232, 249, 0.9);
  animation: pulse-track 2.6s ease-in-out infinite;
}

.target-track.at-left {
  transform: translateX(-160px);
}

.target-track.at-mid {
  transform: translateX(-50%);
}

.target-track.at-right {
  transform: translateX(60px);
}

.bot-stage {
  position: absolute;
  bottom: 28px;
  left: 50%;
  width: 220px;
  height: 220px;
  margin-left: -110px;
  transition: transform 0.35s ease;
}

.bot-stage.lane-left {
  transform: translateX(-128px);
}

.bot-stage.lane-mid {
  transform: translateX(0);
}

.bot-stage.lane-right {
  transform: translateX(128px);
}

.bot-shadow {
  position: absolute;
  bottom: 22px;
  left: 50%;
  width: 96px;
  height: 20px;
  margin-left: -48px;
  border-radius: 999px;
  background: rgba(8, 47, 73, 0.65);
  filter: blur(8px);
}

.module-orbit {
  position: absolute;
  inset: 0;
  animation: rotate 16s linear infinite;
}

.module-orbit.ring-b {
  animation-direction: reverse;
  animation-duration: 22s;
}

.satellite {
  position: absolute;
  left: 50%;
  top: 50%;
  width: 12px;
  height: 12px;
  margin: -6px 0 0 -6px;
  border-radius: 999px;
  box-shadow: 0 0 14px rgba(34, 211, 238, 0.55);
}

.satellite.weapon {
  background: #fb923c;
}

.satellite.defense {
  background: #4ade80;
}

.satellite.mobility {
  background: #38bdf8;
}

.satellite.utility {
  background: #c084fc;
}

.bot-frame {
  position: absolute;
  left: 50%;
  bottom: 26px;
  width: 120px;
  height: 156px;
  margin-left: -60px;
  animation: bob 3s ease-in-out infinite;
}

.bot-head {
  width: 68px;
  height: 42px;
  margin: 0 auto;
  border-radius: 14px;
  border: 1px solid rgba(142, 166, 203, 0.35);
  background: linear-gradient(180deg, rgba(22, 44, 75, 0.95), rgba(10, 24, 44, 0.95));
  display: grid;
  place-items: center;
}

.visor {
  width: 44px;
  height: 14px;
  border-radius: 999px;
  background: rgba(8, 25, 49, 0.85);
  border: 1px solid rgba(56, 189, 248, 0.45);
  overflow: hidden;
}

.visor span {
  display: block;
  width: 18px;
  height: 100%;
  background: linear-gradient(90deg, transparent, #67e8f9, transparent);
  animation: visor-scan 1.4s linear infinite;
}

.bot-antenna {
  width: 3px;
  height: 16px;
  margin: 0 auto 6px;
  background: rgba(56, 189, 248, 0.6);
  position: relative;
}

.bot-antenna::after {
  content: '';
  position: absolute;
  top: -5px;
  left: 50%;
  width: 8px;
  height: 8px;
  margin-left: -4px;
  border-radius: 999px;
  background: #67e8f9;
  box-shadow: 0 0 8px rgba(103, 232, 249, 0.9);
}

.bot-arm,
.bot-leg,
.bot-torso {
  position: absolute;
  border: 1px solid rgba(142, 166, 203, 0.33);
  background: rgba(11, 28, 50, 0.92);
}

.bot-torso {
  width: 78px;
  height: 66px;
  left: 50%;
  top: 52px;
  margin-left: -39px;
  border-radius: 16px;
}

.core-ring {
  position: absolute;
  inset: 16px;
  border-radius: 999px;
  border: 1px solid rgba(56, 189, 248, 0.45);
  animation: spin 5.6s linear infinite;
}

.core-dot {
  position: absolute;
  left: 50%;
  top: 50%;
  width: 10px;
  height: 10px;
  margin: -5px 0 0 -5px;
  border-radius: 999px;
  background: #22d3ee;
  box-shadow: 0 0 12px rgba(34, 211, 238, 0.9);
}

.bot-arm {
  width: 16px;
  height: 56px;
  top: 58px;
  border-radius: 10px;
}

.bot-arm.left {
  left: 10px;
  transform: rotate(10deg);
}

.bot-arm.right {
  right: 10px;
  transform: rotate(-10deg);
}

.bot-leg {
  width: 20px;
  height: 40px;
  bottom: 0;
  border-radius: 10px;
}

.bot-leg.left {
  left: 34px;
}

.bot-leg.right {
  right: 34px;
}

.thrusters {
  position: absolute;
  left: 50%;
  bottom: -10px;
  width: 72px;
  margin-left: -36px;
  display: flex;
  justify-content: space-between;
}

.thrusters span {
  width: 10px;
  height: 22px;
  border-radius: 0 0 10px 10px;
  background: linear-gradient(180deg, rgba(56, 189, 248, 0.2), rgba(56, 189, 248, 0.65), rgba(34, 211, 238, 0));
  animation: flame 0.55s ease-in-out infinite;
}

.thrusters span:last-child {
  animation-delay: 0.2s;
}

.bot-frame.tank .bot-torso,
.bot-frame.tank .bot-head {
  border-color: rgba(74, 222, 128, 0.45);
  background: linear-gradient(180deg, rgba(17, 60, 43, 0.95), rgba(8, 32, 23, 0.95));
}

.bot-frame.striker .bot-torso,
.bot-frame.striker .bot-head {
  border-color: rgba(251, 146, 60, 0.45);
  background: linear-gradient(180deg, rgba(87, 39, 14, 0.95), rgba(45, 19, 8, 0.95));
}

.stats-panel,
.action-row {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.stat-row,
.action-row {
  display: grid;
  grid-template-columns: 54px minmax(0, 1fr) 34px;
  gap: 8px;
  align-items: center;
  font-size: 13px;
}

.bar {
  height: 8px;
  border-radius: 999px;
  overflow: hidden;
  border: 1px solid rgba(142, 166, 203, 0.24);
  background: rgba(9, 23, 43, 0.85);
}

.bar i {
  display: block;
  height: 100%;
}

.bar.hp i,
.bar.attack i {
  background: linear-gradient(90deg, #22d3ee, #3b82f6);
}

.bar.speed i,
.bar.guard i {
  background: linear-gradient(90deg, #34d399, #10b981);
}

.bar.power i,
.bar.shift i {
  background: linear-gradient(90deg, #fb923c, #f97316);
}

.bar.wait i {
  background: linear-gradient(90deg, #94a3b8, #64748b);
}

.intel-card {
  background: rgba(7, 20, 38, 0.72);
}

.module-matrix {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}

.module-matrix > div {
  border: 1px solid rgba(142, 166, 203, 0.2);
  border-radius: 10px;
  padding: 8px;
  display: flex;
  flex-direction: column;
}

.module-matrix label {
  font-size: 11px;
  color: #8ca4c8;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.chip-cloud {
  display: flex;
  flex-wrap: wrap;
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
  border-color: rgba(251, 146, 60, 0.5);
}

.module-chip.defense {
  border-color: rgba(74, 222, 128, 0.5);
}

.module-chip.mobility {
  border-color: rgba(56, 189, 248, 0.5);
}

.module-chip.utility {
  border-color: rgba(192, 132, 252, 0.5);
}

.readiness-line {
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

@keyframes bob {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-8px);
  }
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

@keyframes rotate {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

@keyframes flame {
  0%,
  100% {
    height: 18px;
    opacity: 0.75;
  }
  50% {
    height: 26px;
    opacity: 1;
  }
}

@keyframes visor-scan {
  from {
    transform: translateX(-24px);
  }
  to {
    transform: translateX(42px);
  }
}

@keyframes scan {
  from {
    transform: translateY(-100%);
  }
  to {
    transform: translateY(110%);
  }
}

@keyframes pulse-track {
  0% {
    transform: translateX(0);
    opacity: 0.75;
  }
  50% {
    transform: translateX(232px);
    opacity: 1;
  }
  100% {
    transform: translateX(0);
    opacity: 0.75;
  }
}

@media (max-width: 1100px) {
  .preview-shell {
    grid-template-columns: 1fr;
  }

  .bot-stage.lane-left,
  .bot-stage.lane-right {
    transform: translateX(0);
  }

  .target-track.at-left,
  .target-track.at-right {
    transform: translateX(-50%);
  }
}

@media (prefers-reduced-motion: reduce) {
  .scanline,
  .target-track span,
  .bot-frame,
  .module-orbit,
  .core-ring,
  .thrusters span,
  .visor span {
    animation: none;
  }
}
</style>
