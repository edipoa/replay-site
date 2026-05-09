/* global React, ReactDOM, TweaksPanel, useTweaks, TweakSection, TweakRadio, TweakColor */
const { useEffect } = React;

const VC_TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
  "layout": "grid",
  "accent": "#E8B842",
  "navy": "#0E2A5E"
}/*EDITMODE-END*/;

function VianaTweaks() {
  const [t, setTweak] = useTweaks(VC_TWEAK_DEFAULTS);

  useEffect(() => {
    document.body.setAttribute("data-layout", t.layout);
    document.documentElement.style.setProperty("--accent", t.accent);
    document.documentElement.style.setProperty("--gold", t.accent);
    document.documentElement.style.setProperty("--navy", t.navy);
  }, [t.layout, t.accent, t.navy]);

  return (
    <TweaksPanel title="Tweaks · Viana Clips">
      <TweakSection title="Layout da listagem">
        <TweakRadio
          label="Estilo"
          value={t.layout}
          onChange={(v) => setTweak("layout", v)}
          options={[
            { value: "grid", label: "Grid" },
            { value: "editorial", label: "Editorial" },
            { value: "list", label: "Lista" },
          ]}
        />
      </TweakSection>
      <TweakSection title="Cor de destaque">
        <TweakColor
          label="Accent"
          value={t.accent}
          onChange={(v) => setTweak("accent", v)}
          options={["#E8B842", "#FF6B2B", "#28C76F", "#E63946"]}
        />
        <TweakColor
          label="Azul base"
          value={t.navy}
          onChange={(v) => setTweak("navy", v)}
          options={["#0E2A5E", "#0B1F47", "#142F73", "#101C36"]}
        />
      </TweakSection>
    </TweaksPanel>
  );
}

const root = ReactDOM.createRoot(document.getElementById("tweaks-mount"));
root.render(<VianaTweaks />);
