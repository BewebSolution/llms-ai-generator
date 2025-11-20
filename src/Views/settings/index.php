<h2>Impostazioni</h2>

<?php if (isset($_SESSION['success'])): ?>
    <div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 20px;">
        <?= htmlspecialchars($_SESSION['success']) ?>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 20px;">
        <?= htmlspecialchars($_SESSION['error']) ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<form method="post" action="<?= htmlspecialchars($baseUrl) ?>/settings/update">

    <?php foreach ($settingsByCategory as $category => $settings): ?>
        <?php
        // Salta la categoria system
        if ($category === 'system') continue;
        ?>

        <fieldset style="margin-bottom: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
            <legend style="font-weight: bold; text-transform: capitalize; color: #333;">
                <?php
                $categoryLabels = [
                    'general' => '⚙️ Impostazioni Generali',
                    'openai' => '🤖 Configurazione OpenAI',
                    'storage' => '📁 Storage',
                ];
                echo htmlspecialchars($categoryLabels[$category] ?? $category);
                ?>
            </legend>

            <?php if ($category === 'openai'): ?>
                <!-- Sezione OpenAI personalizzata -->
                <?php
                $openaiSettings = [];
                foreach ($settings as $setting) {
                    $openaiSettings[$setting['setting_key']] = $setting;
                }

                // Debug - vediamo cosa c'è nel database
                error_log("OpenAI settings from DB: " . json_encode($openaiSettings['openai_enabled'] ?? 'not found'));
                ?>

                <!-- Abilita/Disabilita servizio -->
                <div style="margin-bottom: 20px; background: #f0f8ff; padding: 15px; border-radius: 4px;">
                    <label style="display: block; margin-bottom: 5px;">
                        <strong>🔌 Servizio OpenAI</strong>
                    </label>
                    <select name="settings[openai_enabled]"
                            id="openai_enabled"
                            style="width: 200px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
                            onchange="toggleOpenAISettings()">
                        <option value="false" <?= ($openaiSettings['openai_enabled']['setting_value'] ?? 'false') === 'false' ? 'selected' : '' ?>>
                            ❌ Disabilitato
                        </option>
                        <option value="true" <?= ($openaiSettings['openai_enabled']['setting_value'] ?? 'false') === 'true' ? 'selected' : '' ?>>
                            ✅ Abilitato
                        </option>
                    </select>
                    <small style="display: block; color: #666; margin-top: 5px;">
                        Abilita per generare automaticamente descrizioni brevi con AI
                    </small>
                </div>

                <div id="openai_settings" style="display: <?= ($openaiSettings['openai_enabled']['setting_value'] ?? 'false') === 'true' ? 'block' : 'none' ?>;">

                    <!-- API Key -->
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 5px;">
                            <strong>🔑 Chiave API OpenAI</strong>
                        </label>
                        <input type="password"
                               name="settings[openai_api_key]"
                               value="<?= htmlspecialchars($openaiSettings['openai_api_key']['setting_value'] ?? '') ?>"
                               placeholder="sk-..."
                               style="width: 500px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <small style="display: block; color: #666; margin-top: 5px;">
                            Ottieni la tua API key da <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a>
                        </small>
                    </div>

                    <!-- Modello -->
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 5px;">
                            <strong>🧠 Modello OpenAI</strong>
                        </label>
                        <select name="settings[openai_model]"
                                style="width: 300px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <?php
                            $models = [
                                'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Veloce ed economico)',
                                'gpt-3.5-turbo-16k' => 'GPT-3.5 Turbo 16K (Context esteso)',
                                'gpt-4' => 'GPT-4 (Più intelligente)',
                                'gpt-4-turbo-preview' => 'GPT-4 Turbo (Più veloce)',
                                'gpt-4o' => 'GPT-4o (Ottimizzato)',
                                'gpt-4o-mini' => 'GPT-4o Mini (Economico)'
                            ];
                            $currentModel = $openaiSettings['openai_model']['setting_value'] ?? 'gpt-3.5-turbo';
                            ?>
                            <?php foreach ($models as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value) ?>"
                                        <?= $currentModel === $value ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="display: block; color: #666; margin-top: 5px;">
                            Scegli il modello in base a velocità, costo e qualità
                        </small>
                    </div>

                    <!-- Temperature -->
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 5px;">
                            <strong>🌡️ Temperature</strong>
                        </label>
                        <input type="number"
                               name="settings[openai_temperature]"
                               value="<?= htmlspecialchars($openaiSettings['openai_temperature']['setting_value'] ?? '0.7') ?>"
                               min="0"
                               max="2"
                               step="0.1"
                               style="width: 100px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <small style="display: block; color: #666; margin-top: 5px;">
                            0 = Deterministico, 1 = Bilanciato, 2 = Creativo (Consigliato: 0.7)
                        </small>
                    </div>

                    <!-- Test connessione -->
                    <div style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                        <button type="button"
                                onclick="testOpenAIConnection()"
                                style="background: #10a37f; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">
                            🔍 Test Connessione OpenAI
                        </button>
                        <span id="openai-test-result" style="margin-left: 15px;"></span>
                    </div>
                </div>

            <?php elseif ($category === 'general'): ?>
                <!-- Sezione General semplificata -->
                <?php foreach ($settings as $setting): ?>
                    <?php if ($setting['setting_key'] === 'app_debug'): ?>
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px;">
                                <strong><?= htmlspecialchars($setting['description'] ?: $setting['setting_key']) ?></strong>
                            </label>
                            <select name="settings[<?= htmlspecialchars($setting['setting_key']) ?>]"
                                    style="width: 200px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="true" <?= $setting['setting_value'] === 'true' ? 'selected' : '' ?>>
                                    Abilitato
                                </option>
                                <option value="false" <?= $setting['setting_value'] === 'false' ? 'selected' : '' ?>>
                                    Disabilitato
                                </option>
                            </select>
                        </div>
                    <?php else: ?>
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px;">
                                <strong><?= htmlspecialchars($setting['description'] ?: $setting['setting_key']) ?></strong>
                            </label>
                            <input type="text"
                                   name="settings[<?= htmlspecialchars($setting['setting_key']) ?>]"
                                   value="<?= htmlspecialchars($setting['setting_value']) ?>"
                                   style="width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

            <?php else: ?>
                <!-- Altre sezioni standard -->
                <?php foreach ($settings as $setting): ?>
                    <?php
                    $key = $setting['setting_key'];
                    $value = $setting['setting_value'];
                    $type = $setting['setting_type'];
                    $description = $setting['description'];
                    ?>

                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;">
                            <strong><?= htmlspecialchars($description ?: $key) ?></strong>
                        </label>

                        <?php if ($type === 'boolean'): ?>
                            <select name="settings[<?= htmlspecialchars($key) ?>]"
                                    style="width: 200px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="true" <?= $value === 'true' ? 'selected' : '' ?>>Abilitato</option>
                                <option value="false" <?= $value === 'false' ? 'selected' : '' ?>>Disabilitato</option>
                            </select>
                        <?php else: ?>
                            <input type="text"
                                   name="settings[<?= htmlspecialchars($key) ?>]"
                                   value="<?= htmlspecialchars($value) ?>"
                                   style="width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </fieldset>
    <?php endforeach; ?>

    <p>
        <button type="submit" style="background: #4CAF50; color: white; padding: 12px 30px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">
            💾 Salva Impostazioni
        </button>
        <a href="<?= htmlspecialchars($baseUrl) ?>/" style="margin-left: 10px; padding: 12px 20px; display: inline-block;">
            Annulla
        </a>
    </p>
</form>

<script>
function toggleOpenAISettings() {
    const enabled = document.getElementById('openai_enabled').value === 'true';
    document.getElementById('openai_settings').style.display = enabled ? 'block' : 'none';
}

async function testOpenAIConnection() {
    const resultSpan = document.getElementById('openai-test-result');
    resultSpan.innerHTML = '<span style="color: #666;">⏳ Testing...</span>';

    const form = document.querySelector('form');
    const formData = new FormData();

    // Ottieni i valori correnti dal form
    const keyInput = form.querySelector('input[name="settings[openai_api_key]"]');
    const modelSelect = form.querySelector('select[name="settings[openai_model]"]');
    const tempInput = form.querySelector('input[name="settings[openai_temperature]"]');

    if (!keyInput.value) {
        resultSpan.innerHTML = '<span style="color: #dc3545;">❌ Inserisci prima la API Key</span>';
        return;
    }

    formData.append('api_key', keyInput.value);
    formData.append('model', modelSelect.value);
    formData.append('temperature', tempInput.value);

    try {
        const response = await fetch('<?= htmlspecialchars($baseUrl) ?>/settings/test-openai', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            resultSpan.innerHTML = '<span style="color: #28a745;">✅ ' + data.message + '</span>';
        } else {
            resultSpan.innerHTML = '<span style="color: #dc3545;">❌ ' + data.message + '</span>';
        }
    } catch (error) {
        resultSpan.innerHTML = '<span style="color: #dc3545;">❌ Errore: ' + error.message + '</span>';
    }
}

// Inizializza visibilità al caricamento
toggleOpenAISettings();
</script>