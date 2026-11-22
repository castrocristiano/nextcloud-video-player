import fs from "node:fs";
import path from "node:path";
import { describe, it, expect, beforeEach, afterEach } from "vitest";
import { getLocale, n, t, translate, translatePlural } from "../../js/modules/i18n.js";

describe("i18n module and l10n pt_BR catalog parity", () => {
  beforeEach(() => {
    delete window.t;
    delete window.n;
    delete window.OC;
    document.documentElement.lang = "";
  });

  afterEach(() => {
    delete window.t;
    delete window.n;
    delete window.OC;
  });

  it("falls back to English when no Nextcloud translator is registered", () => {
    expect(t("Video Player")).toBe("Video Player");
    expect(t("Resuming playback from {time}.", { time: "05:12" })).toBe(
      "Resuming playback from 05:12."
    );
    expect(n("%n folder selected", "%n folders selected", 1)).toBe(
      "1 folder selected"
    );
    expect(n("%n folder selected", "%n folders selected", 3)).toBe(
      "3 folders selected"
    );
    expect(getLocale()).toBe("en");
  });

  it("uses Nextcloud OC.L10N and pt_BR catalog when registered", () => {
    const jsonPath = path.resolve(process.cwd(), "l10n/pt_BR.json");
    const jsPath = path.resolve(process.cwd(), "l10n/pt_BR.js");
    const catalogJson = JSON.parse(fs.readFileSync(jsonPath, "utf8"));

    let registeredApp = "";
    let registeredDict = {};
    let registeredPlural = "";

    window.OC = {
      getLocale: () => "pt_BR",
      L10N: {
        register: (app, dict, plural) => {
          registeredApp = app;
          registeredDict = dict;
          registeredPlural = plural;
        },
        translate: (app, text) => registeredDict[text] || text,
        translatePlural: (app, singular, plural, count) => {
          const tpl =
            count === 1
              ? registeredDict[singular] || singular
              : registeredDict[plural] || plural;
          return tpl.replace(/%n/g, String(count));
        },
      },
    };

    const jsCode = fs.readFileSync(jsPath, "utf8");
    const runner = new Function("OC", jsCode);
    runner(window.OC);

    expect(registeredApp).toBe("folder_video_player");
    expect(registeredPlural).toBe(catalogJson.pluralForm);
    expect(Object.keys(registeredDict)).toEqual(
      Object.keys(catalogJson.translations)
    );

    for (const [key, val] of Object.entries(catalogJson.translations)) {
      expect(key.length).toBeGreaterThan(0);
      expect(val).toBeTruthy();
    }

    expect(getLocale()).toBe("pt-BR");
    expect(translate("Library Folders")).toBe("Pastas da Biblioteca");
    expect(
      translate("Resuming playback from {time}.", { time: "10:00" })
    ).toBe("Retomando reprodução a partir de 10:00.");
    expect(
      translatePlural("%n folder selected", "%n folders selected", 1)
    ).toBe("1 pasta selecionada");
    expect(
      translatePlural("%n folder selected", "%n folders selected", 4)
    ).toBe("4 pastas selecionadas");
    // Missing key falls back cleanly to English source string
    expect(translate("Untranslated Future Key")).toBe(
      "Untranslated Future Key"
    );
  });

  it("supports window.t and window.n global helpers and documentElement.lang fallback", () => {
    document.documentElement.lang = "pt_BR";
    expect(getLocale()).toBe("pt-BR");

    window.t = (_app, text) => (text === "Close" ? "Fechar" : text);
    window.n = (_app, s, p, c) => (c === 1 ? `1 item` : `${c} itens`);

    expect(t("Close")).toBe("Fechar");
    expect(n("%n item", "%n items", 1)).toBe("1 item");
    expect(n("%n item", "%n items", 2)).toBe("2 itens");
  });
});
