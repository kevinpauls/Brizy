import classnames from "classnames";
import { css } from "glamor";
import { hexToRgba } from "visual/utils/color";

const getIconStrokeWidth = (v, iconSize) => {
  const { type } = v;

  return type === "outline" && iconSize <= 24
    ? 1
    : type === "outline" && iconSize > 24 && iconSize <= 32
      ? 1.1
      : type === "outline" && iconSize > 32 && iconSize <= 48
        ? 1.4
        : type === "outline" && iconSize > 48 && iconSize <= 64
          ? 2.3
          : type === "outline" && iconSize > 64 ? 3 : 0;
};

export function styleClassName(v) {
  const { className } = v;
  let glamorObj;

  if (IS_EDITOR) {
    glamorObj = {
      ".brz &": {
        color: "var(--color)",
        borderColor: "var(--borderColor)",
        backgroundColor: "var(--backgroundColor)",
        borderWidth: "var(--borderWidth)",
        borderStyle: "var(--borderStyle)",
        boxShadow: "var(--boxShadow)"
      },
      ".brz &:hover": {
        color: "var(--hoverColor)",
        borderColor: "var(--hoverBorderColor)",
        backgroundColor: "var(--hoverBgColor)"
      },
      ".brz-ed--desktop &": {
        width: "var(--width)",
        height: "var(--height)",
        fontSize: "var(--fontSize)",
        padding: "var(--padding)",
        borderRadius: "var(--borderRadius)",
        strokeWidth: "var(--strokeWidth)",
      },
      ".brz-ed--mobile &": {
        width: "var(--mobileWidth)",
        height: "var(--mobileHeight)",
        fontSize: "var(--mobileFontSize)",
        padding: "var(--mobilePadding)",
        borderRadius: "var(--mobileBorderRadius)",
        strokeWidth: "var(--mobileStrokeWidth)"
      }
    };
  } else {
    const {
      colorHex,
      colorOpacity,
      bgColorHex,
      bgColorOpacity,
      borderColorHex,
      borderColorOpacity,
      hoverColorHex,
      hoverColorOpacity,
      hoverBgColorHex,
      hoverBgColorOpacity,
      hoverBorderColorHex,
      hoverBorderColorOpacity,
      borderWidth,
      borderStyle,
      customSize,
      padding,
      borderRadius,
      boxShadow,
      boxShadowColorHex,
      boxShadowColorOpacity,
      boxShadowBlur,
      boxShadowSpread,
      boxShadowVertical,
      boxShadowHorizontal,
      mobileCustomSize,
      mobilePadding,
      mobileBorderRadius
    } = v;
    const iconSize = Math.round(customSize + padding * 2 + borderWidth * 2);
    const mobileIconSize = Math.round(
      mobileCustomSize + mobilePadding * 2 + borderWidth * 2
    );
    const strokeWidth = getIconStrokeWidth(v, customSize);
    const mobileStrokeWidth = getIconStrokeWidth(v, mobileCustomSize);

    const boxShadowStyle =
      boxShadow === "on"
        ? `${boxShadowHorizontal}px ${boxShadowVertical}px ${boxShadowBlur}px ${boxShadowSpread}px ${hexToRgba(
        boxShadowColorHex,
        boxShadowColorOpacity
        )}`
        : "none";

    glamorObj = {
      ".brz &": {
        color: hexToRgba(colorHex, colorOpacity),
        borderColor: hexToRgba(borderColorHex, borderColorOpacity),
        backgroundColor: hexToRgba(bgColorHex, bgColorOpacity),
        borderWidth,
        borderStyle,
        width: `${iconSize}px`,
        height: `${iconSize}px`,
        fontSize: `${customSize}px`,
        padding: `${padding}px`,
        borderRadius,
        strokeWidth,
        boxShadow: boxShadowStyle
      },
      ".brz &:hover": {
        color: hexToRgba(hoverColorHex, hoverColorOpacity),
        borderColor: hexToRgba(hoverBorderColorHex, hoverBorderColorOpacity),
        backgroundColor: hexToRgba(hoverBgColorHex, hoverBgColorOpacity)
      },
      "@media (max-width: 767px)": {
        ".brz &": {
          width: `${mobileIconSize}px`,
          height: `${mobileIconSize}px`,
          fontSize: `${mobileCustomSize}px`,
          padding: `${mobilePadding}px`,
          borderRadius: `${mobileBorderRadius}px`,
          strokeWidth: mobileStrokeWidth
        }
      }
    };
  }

  const glamorClassName = String(css(glamorObj));

  return classnames("brz-span brz-icon", glamorClassName, className);
}

export function styleCSSVars(v) {
  if (IS_PREVIEW) return;

  const {
    colorHex,
    colorOpacity,
    bgColorHex,
    bgColorOpacity,
    borderColorHex,
    borderColorOpacity,
    hoverColorHex,
    hoverColorOpacity,
    hoverBgColorHex,
    hoverBgColorOpacity,
    hoverBorderColorHex,
    hoverBorderColorOpacity,
    borderWidth,
    borderStyle,
    customSize,
    padding,
    borderRadius,
    boxShadow,
    boxShadowColorHex,
    boxShadowColorOpacity,
    boxShadowBlur,
    boxShadowSpread,
    boxShadowVertical,
    boxShadowHorizontal,
    mobileCustomSize,
    mobilePadding,
    mobileBorderRadius
  } = v;

  const iconSize = Math.round(customSize + padding * 2 + borderWidth * 2);
  const mobileIconSize = Math.round(
    mobileCustomSize + mobilePadding * 2 + borderWidth * 2
  );

  const strokeWidth = getIconStrokeWidth(v, customSize);
  const mobileStrokeWidth = getIconStrokeWidth(v, mobileCustomSize);

  const boxShadowStyle =
    boxShadow === "on"
      ? `${boxShadowHorizontal}px ${boxShadowVertical}px ${boxShadowBlur}px ${boxShadowSpread}px ${hexToRgba(
      boxShadowColorHex,
      boxShadowColorOpacity
      )}`
      : "none";

  return {
    "--color": hexToRgba(colorHex, colorOpacity),
    "--borderColor": hexToRgba(borderColorHex, borderColorOpacity),
    "--backgroundColor": hexToRgba(bgColorHex, bgColorOpacity),
    "--borderWidth": `${borderWidth}px`,
    "--borderStyle": borderStyle,
    "--hoverColor": hexToRgba(hoverColorHex, hoverColorOpacity),
    "--hoverBorderColor": hexToRgba(
      hoverBorderColorHex,
      hoverBorderColorOpacity
    ),
    "--hoverBgColor": hexToRgba(hoverBgColorHex, hoverBgColorOpacity),
    "--width": `${iconSize}px`,
    "--height": `${iconSize}px`,
    "--fontSize": `${customSize}px`,
    "--padding": `${padding}px`,
    "--borderRadius": `${borderRadius}px`,
    "--strokeWidth": strokeWidth,
    "--boxShadow": boxShadowStyle,
    "--mobileWidth": `${mobileIconSize}px`,
    "--mobileHeight": `${mobileIconSize}px`,
    "--mobileFontSize": `${mobileCustomSize}px`,
    "--mobilePadding": `${mobilePadding}px`,
    "--mobileBorderRadius": `${mobileBorderRadius}px`,
    "--mobileStrokeWidth": mobileStrokeWidth
  };
}
