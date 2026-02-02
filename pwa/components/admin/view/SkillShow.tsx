import { ShowGuesser, FieldGuesser } from "@api-platform/admin";

export const SkillShow = () => (
  <ShowGuesser>
    <FieldGuesser source="title" />
    <FieldGuesser source="category.title" label="Category" />
    <FieldGuesser source="createdAt" />
    <FieldGuesser source="updatedAt" />
  </ShowGuesser>
);
