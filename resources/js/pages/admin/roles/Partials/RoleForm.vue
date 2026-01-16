<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Save } from 'lucide-vue-next';
import { computed, ref, watchEffect } from 'vue';

type PermissionRow = {
    id: number;
    name: string;
};

type RoleRow = {
    id: number;
    name: string;
    guard_name: string;
    permissions?: PermissionRow[];
};

const props = defineProps<{
    role?: RoleRow;
    permissions: PermissionRow[];
    submitLabel: string;
    processing: boolean;
    errors: Record<string, string>;
}>();

const selectedPermissionIds = ref<number[]>([]);

watchEffect(() => {
    if (props.role?.permissions) {
        selectedPermissionIds.value = props.role.permissions.map((p) => p.id);
    } else {
        selectedPermissionIds.value = [];
    }
});

const permissionSearch = ref('');

// Função para obter o nome do grupo a partir do nome da permissão
const getGroupName = (permissionName: string): string => {
    const parts = permissionName.split('.');
    if (parts.length > 1) {
        return parts[0];
    }
    return 'Outros';
};

// Função para obter o nome amigável do grupo
const getGroupLabel = (groupName: string): string => {
    const labels: Record<string, string> = {
        escolas: 'Escolas',
        escola: 'Escola',
        usuarios: 'Usuários',
        roles: 'Roles',
        permissoes: 'Permissões',
        assinaturas: 'Assinaturas',
        planos: 'Planos',
        logs: 'Logs do Sistema',
    };
    return labels[groupName] || groupName.charAt(0).toUpperCase() + groupName.slice(1);
};

// Função para obter o label amigável do subgrupo
const getSubgroupLabel = (subgroupName: string): string => {
    const labels: Record<string, string> = {
        perfil: 'Perfil',
        alunos: 'Alunos',
        responsaveis: 'Responsáveis',
        professores: 'Professores',
        turmas: 'Turmas',
        exercicios: 'Exercícios',
        provas: 'Provas',
        mensagens: 'Mensagens',
        disciplinas: 'Disciplinas',
    };
    return labels[subgroupName] || subgroupName.charAt(0).toUpperCase() + subgroupName.slice(1);
};

// Função para obter a ação da permissão (parte após o ponto)
const getPermissionAction = (permissionName: string): string => {
    const parts = permissionName.split('.');
    if (parts.length > 1) {
        return parts[parts.length - 1];
    }
    return permissionName;
};

// Função para obter o label amigável da ação
const getActionLabel = (action: string): string => {
    const labels: Record<string, string> = {
        visualizar: 'Visualizar',
        criar: 'Criar',
        editar: 'Editar',
        excluir: 'Excluir',
    };
    return labels[action] || action;
};

// Agrupar permissões filtradas por módulo
const groupedPermissions = computed(() => {
    const term = permissionSearch.value.trim().toLowerCase();
    let filtered = props.permissions;
    
    if (term) {
        filtered = props.permissions.filter((p) => 
            p.name.toLowerCase().includes(term)
        );
    }

    // Separar permissões de escola das demais
    const escolaPermissions: PermissionRow[] = [];
    const otherPermissions: PermissionRow[] = [];
    
    filtered.forEach((permission) => {
        const groupName = getGroupName(permission.name);
        if (groupName === 'escola') {
            escolaPermissions.push(permission);
        } else {
            otherPermissions.push(permission);
        }
    });

    // Agrupar outras permissões normalmente
    const otherGroups: Record<string, PermissionRow[]> = {};
    otherPermissions.forEach((permission) => {
        const groupName = getGroupName(permission.name);
        if (!otherGroups[groupName]) {
            otherGroups[groupName] = [];
        }
        otherGroups[groupName].push(permission);
    });

    // Agrupar permissões de escola por subgrupo
    const escolaSubgroups: Record<string, PermissionRow[]> = {};
    escolaPermissions.forEach((permission) => {
        const parts = permission.name.split('.');
        if (parts.length >= 2) {
            const subgroupName = parts[1];
            if (!escolaSubgroups[subgroupName]) {
                escolaSubgroups[subgroupName] = [];
            }
            escolaSubgroups[subgroupName].push(permission);
        } else {
            // Se não tiver subgrupo, adiciona a um grupo "outros"
            if (!escolaSubgroups['outros']) {
                escolaSubgroups['outros'] = [];
            }
            escolaSubgroups['outros'].push(permission);
        }
    });

    // Ordenar grupos de outras permissões
    const sortedOtherGroups: Record<string, PermissionRow[]> = {};
    Object.keys(otherGroups)
        .sort()
        .forEach((groupName) => {
            sortedOtherGroups[groupName] = otherGroups[groupName].sort((a, b) => 
                a.name.localeCompare(b.name)
            );
        });

    // Ordenar subgrupos de escola
    const sortedEscolaSubgroups: Record<string, PermissionRow[]> = {};
    Object.keys(escolaSubgroups)
        .sort()
        .forEach((subgroupName) => {
            sortedEscolaSubgroups[subgroupName] = escolaSubgroups[subgroupName].sort((a, b) => 
                a.name.localeCompare(b.name)
            );
        });

    // Retornar estrutura combinada
    return {
        escola: escolaPermissions.length > 0 ? sortedEscolaSubgroups : null,
        others: sortedOtherGroups,
    };
});

// Função para lidar com a mudança de seleção de permissão
const handlePermissionChange = (permissionId: number, checked: boolean) => {
    if (checked) {
        if (!selectedPermissionIds.value.includes(permissionId)) {
            selectedPermissionIds.value.push(permissionId);
        }
    } else {
        selectedPermissionIds.value = selectedPermissionIds.value.filter(
            (id: number) => id !== permissionId,
        );
    }
};
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-2">
            <Label for="name">Nome</Label>
            <Input
                id="name"
                name="name"
                :default-value="role?.name ?? ''"
                placeholder="Ex: admin"
                required
            />
            <InputError :message="errors.name" />
        </div>

        <div class="grid gap-2">
            <div class="flex items-end justify-between gap-3">
                <Label>Permissões</Label>
                <div class="w-64">
                    <Input
                        v-model="permissionSearch"
                        placeholder="Buscar permissões..."
                    />
                </div>
            </div>

            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div v-if="groupedPermissions.escola || Object.keys(groupedPermissions.others).length > 0" class="space-y-6">
                    <!-- Grupo Escola com subgrupos -->
                    <div v-if="groupedPermissions.escola" class="space-y-4">
                        <h3 class="text-sm font-semibold text-foreground">
                            {{ getGroupLabel('escola') }}
                        </h3>
                        <div
                            v-for="(permissions, subgroupName) in groupedPermissions.escola"
                            :key="subgroupName"
                            class="space-y-3 ml-4"
                        >
                            <h4 class="text-sm font-medium text-foreground/80">
                                {{ getSubgroupLabel(subgroupName) }}
                            </h4>
                            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                <label
                                    v-for="p in permissions"
                                    :key="p.id"
                                    class="flex items-center gap-2 rounded-md border border-input bg-background px-3 py-2 text-sm hover:bg-accent transition-colors"
                                >
                                    <input
                                        type="checkbox"
                                        name="permissions[]"
                                        :value="p.id"
                                        class="h-4 w-4 rounded border border-input"
                                        :checked="selectedPermissionIds.includes(p.id)"
                                        @change="
                                            (e) => {
                                                handlePermissionChange(
                                                    p.id,
                                                    (e.target as HTMLInputElement).checked,
                                                );
                                            }
                                        "
                                    />
                                    <span class="text-muted-foreground">
                                        {{ getActionLabel(getPermissionAction(p.name)) }}
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Outros grupos -->
                    <div
                        v-for="(permissions, groupName) in groupedPermissions.others"
                        :key="groupName"
                        class="space-y-3"
                    >
                        <h3 class="text-sm font-semibold text-foreground">
                            {{ getGroupLabel(groupName) }}
                        </h3>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <label
                                v-for="p in permissions"
                                :key="p.id"
                                class="flex items-center gap-2 rounded-md border border-input bg-background px-3 py-2 text-sm hover:bg-accent transition-colors"
                            >
                                <input
                                    type="checkbox"
                                    name="permissions[]"
                                    :value="p.id"
                                    class="h-4 w-4 rounded border border-input"
                                    :checked="selectedPermissionIds.includes(p.id)"
                                    @change="
                                        (e) => {
                                            handlePermissionChange(
                                                p.id,
                                                (e.target as HTMLInputElement).checked,
                                            );
                                        }
                                    "
                                />
                                <span class="text-muted-foreground">
                                    {{ getActionLabel(getPermissionAction(p.name)) }}
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <p
                    v-else
                    class="py-6 text-center text-sm text-muted-foreground"
                >
                    Nenhuma permissão encontrada.
                </p>
            </div>

            <InputError :message="errors.permissions" />
        </div>

        <div class="flex items-center justify-end gap-2">
            <Button type="submit" :disabled="processing" class="flex items-center gap-2">
                <Save class="h-4 w-4" />
                {{ submitLabel }}
            </Button>
        </div>
    </div>
</template>


