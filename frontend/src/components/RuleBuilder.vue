<template>
  <div class="panel col">
    <h3>Ruleset Builder</h3>
    <label>
      Ruleset Name
      <input v-model="local.name" placeholder="Aggro Priority" />
    </label>

    <div class="list">
      <div v-for="(rule, idx) in local.rules" :key="idx" class="card col">
        <div class="row">
          <strong>Rule {{ idx + 1 }}</strong>
          <button class="ghost" type="button" @click="removeRule(idx)">Remove</button>
        </div>
        <label>
          Priority
          <input type="number" v-model.number="rule.priority" min="0" max="1000" />
        </label>
        <div class="grid two">
          <label>
            Sensor
            <select v-model="rule.when.sensor">
              <option value="self_hp_pct">Self HP %</option>
              <option value="enemy_hp_pct">Enemy HP %</option>
              <option value="self_lane">Self Lane</option>
              <option value="enemy_lane">Enemy Lane</option>
              <option value="tick">Tick</option>
              <option value="cooldown_ready">Cooldown Ready</option>
            </select>
          </label>
          <label>
            Operator
            <select v-model="rule.when.op">
              <option value="==">==</option>
              <option value="!=">!=</option>
              <option value=">">&gt;</option>
              <option value=">=">&gt;=</option>
              <option value="<">&lt;</option>
              <option value="<=">&lt;=</option>
            </select>
          </label>
          <label>
            Value
            <input v-model="rule.when.value" placeholder="e.g. 50 or left" />
          </label>
        </div>
        <div class="grid two">
          <label>
            Action
            <select v-model="rule.then.action">
              <option value="attack_lane">Attack Lane</option>
              <option value="guard">Guard</option>
              <option value="shift_lane">Shift Lane</option>
              <option value="wait">Wait</option>
            </select>
          </label>
          <label>
            Lane Param
            <select v-model="rule.then.params.lane">
              <option value="left">Left</option>
              <option value="mid">Mid</option>
              <option value="right">Right</option>
            </select>
          </label>
        </div>
      </div>
    </div>

    <button type="button" class="ghost" @click="addRule">Add Rule</button>
  </div>
</template>

<script setup lang="ts">
import { reactive, watch } from 'vue';

const props = defineProps<{ modelValue: any }>();
const emit = defineEmits<{ (e: 'update:modelValue', value: any): void }>();

const local = reactive<any>({
  name: props.modelValue?.name ?? '',
  rules: Array.isArray(props.modelValue?.rules) ? [...props.modelValue.rules] : []
});

watch(
  () => local,
  () => {
    const normalized = {
      name: local.name,
      rules: local.rules.map((rule: any) => ({
        priority: Number(rule.priority ?? 0),
        when: {
          sensor: rule.when?.sensor ?? 'tick',
          op: rule.when?.op ?? '>=',
          value: normalizeValue(rule.when?.value)
        },
        then: {
          action: rule.then?.action ?? 'wait',
          params: {
            lane: rule.then?.params?.lane ?? 'mid'
          }
        }
      }))
    };
    emit('update:modelValue', normalized);
  },
  { deep: true }
);

function normalizeValue(value: any) {
  if (typeof value === 'string' && /^\d+$/.test(value)) {
    return Number(value);
  }
  if (value === 'true') {
    return true;
  }
  if (value === 'false') {
    return false;
  }
  return value;
}

function addRule() {
  local.rules.push({
    priority: local.rules.length,
    when: { sensor: 'tick', op: '>=', value: 1 },
    then: { action: 'attack_lane', params: { lane: 'mid' } }
  });
}

function removeRule(index: number) {
  local.rules.splice(index, 1);
}
</script>
